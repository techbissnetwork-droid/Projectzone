<?php
declare(strict_types=1);

namespace Techbiss\Core;

/**
 * Secure file uploads.
 *
 * Files are accepted only when the real MIME type (read from the file contents,
 * not from the browser) is on the allow-list, the extension is derived from that
 * MIME type rather than from the submitted filename, and — for images — the
 * dimensions are sane. Uploads are written with a generated name into
 * /uploads, which is configured to execute nothing.
 */
final class Uploader
{
    private array $cfg;
    private string $root;

    public function __construct(array $uploadConfig, string $projectRoot)
    {
        $this->cfg  = $uploadConfig;
        $this->root = rtrim($projectRoot, '/');
    }

    /**
     * @return array{ok:bool,error:string,data:array<string,mixed>}
     */
    /**
     * @param array<string,string>|null $allowedOverride MIME => extension, replacing the
     *        configured allow-list for this one call. Used by the APK field, which accepts
     *        a type the media library deliberately does not.
     */
    public function store(array $file, string $folder = 'general', ?array $allowedOverride = null): array
    {
        $fail = static fn (string $msg): array => ['ok' => false, 'error' => $msg, 'data' => []];

        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            return $fail($this->errorMessage($error));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || (!is_uploaded_file($tmp) && PHP_SAPI !== 'cli')) {
            return $fail('The upload could not be verified.');
        }
        if (!is_file($tmp)) {
            return $fail('The uploaded file is missing.');
        }

        $size = (int) ($file['size'] ?? filesize($tmp));
        $max  = (int) $this->cfg['max_bytes'];
        if ($size <= 0) {
            return $fail('The file is empty.');
        }
        if ($size > $max) {
            return $fail('The file is larger than ' . $this->humanBytes($max) . '.');
        }

        // Trust the file contents, never the client-supplied type.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($tmp);
        $allowed = $allowedOverride ?? $this->cfg['allowed_mime'];
        if (!isset($allowed[$mime])) {
            return $fail('That file type is not allowed. Accepted types: ' . implode(', ', array_unique(array_values($allowed))) . '.');
        }
        $ext = (string) $allowed[$mime];

        // An APK is a ZIP, and so are a great many other things. Reading the
        // MIME type alone would let any archive through under an .apk name, so
        // the contents are checked for the manifest every Android package has.
        if ($ext === 'apk' && !self::looksLikeApk($tmp)) {
            return $fail('That file is not an Android package. An APK must contain AndroidManifest.xml.');
        }

        $width = $height = null;
        if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') {
            $info = @getimagesize($tmp);
            if ($info === false) {
                return $fail('That image could not be read.');
            }
            [$width, $height] = [(int) $info[0], (int) $info[1]];
            if ($width < 1 || $height < 1) {
                return $fail('That image has invalid dimensions.');
            }
            if ($width > (int) $this->cfg['max_width'] || $height > (int) $this->cfg['max_height']) {
                return $fail('That image is larger than ' . $this->cfg['max_width'] . '×' . $this->cfg['max_height'] . ' pixels.');
            }
        }

        if ($mime === 'image/svg+xml' && !self::svgIsSafe((string) file_get_contents($tmp))) {
            return $fail('That SVG contains scripting and was rejected.');
        }

        $folder = preg_match('/^[a-z0-9_-]{1,40}$/', $folder) ? $folder : 'general';
        $subDir = 'uploads/media/' . $folder . '/' . date('Y/m');
        $absDir = $this->root . '/' . $subDir;
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            return $fail('The upload directory could not be created.');
        }

        $originalName = (string) ($file['name'] ?? 'file');
        $base         = Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $fileName     = mb_substr($base, 0, 60) . '-' . substr(bin2hex(random_bytes(6)), 0, 10) . '.' . $ext;
        $absPath      = $absDir . '/' . $fileName;

        $moved = PHP_SAPI === 'cli' ? @rename($tmp, $absPath) : @move_uploaded_file($tmp, $absPath);
        if (!$moved) {
            return $fail('The file could not be saved to disk.');
        }
        @chmod($absPath, 0644);

        $relPath   = $subDir . '/' . $fileName;
        $thumbPath = '';
        if ($width !== null && $height !== null) {
            $thumbPath = $this->makeThumbnail($absPath, $mime, $folder, $fileName, $width, $height);
        }

        return [
            'ok'    => true,
            'error' => '',
            'data'  => [
                'file_name'     => $fileName,
                'original_name' => mb_substr($originalName, 0, 255),
                'path'          => $relPath,
                'thumb_path'    => $thumbPath,
                'mime_type'     => $mime,
                'extension'     => $ext,
                'size_bytes'    => $size,
                'width'         => $width,
                'height'        => $height,
                'folder'        => $folder,
            ],
        ];
    }

    /**
     * Is this SVG free of anything that can execute?
     *
     * A plain substring scan is not enough: an SVG is XML, so a scheme can be
     * written as character references and still run — &#106;avascript: is
     * javascript:. Entities are resolved first, then the check looks for
     * scripting elements, event handlers, and any link whose scheme is not one
     * we allow.
     */
    private static function svgIsSafe(string $svg): bool
    {
        // Resolve character references so an encoded scheme cannot slip past,
        // and drop whitespace that XML tolerates inside an attribute value.
        $probe = html_entity_decode($svg, ENT_QUOTES | ENT_HTML5 | ENT_XML1, 'UTF-8');
        $probe = preg_replace('/&#x?0*(?:9|10|13|32);/i', '', $probe) ?? $probe;
        $probe = preg_replace('/[\x00-\x20]+/', ' ', $probe) ?? $probe;

        // Elements and constructs that can run code or pull in a remote document.
        // The optional "prefix:" allows for a namespaced spelling such as
        // <s:script xmlns:s="http://www.w3.org/2000/svg">: that is still a real
        // SVG script element and executes, so it must be caught here too — a
        // bare "script" match alone would let the prefixed form straight through.
        if (preg_match('/<\s*(?:[a-z][\w.\-]*:)?(script|foreignObject|iframe|embed|object|handler|set|animate|animateTransform|animateMotion)\b/i', $probe)) {
            return false;
        }
        // An inline DTD can define entities that expand into anything.
        if (preg_match('/<!\s*(ENTITY|DOCTYPE)\b/i', $probe)) {
            return false;
        }
        // on* handlers, in any casing, with or without spaces around the equals.
        if (preg_match('/\bon[a-z]+\s*=/i', $probe)) {
            return false;
        }
        // Any link target must use a scheme we are willing to allow. Fragments,
        // relative paths, http(s) and data:image are fine; everything else —
        // javascript:, vbscript:, data:text/html — is not.
        if (preg_match_all('/\b(?:xlink:)?href\s*=\s*["\']([^"\']*)["\']/i', $probe, $m)) {
            foreach ($m[1] as $target) {
                // Browsers ignore whitespace inside a scheme, so "java\nscript:"
                // runs. Strip it all before deciding what the scheme is.
                $target = preg_replace('/\s+/', '', $target) ?? $target;
                if ($target === '' || $target[0] === '#' || $target[0] === '/') {
                    continue;
                }
                if (preg_match('#^https?://#i', $target)) {
                    continue;
                }
                if (preg_match('#^data:image/(png|jpe?g|gif|webp);base64,#i', $target)) {
                    continue;
                }
                if (!preg_match('/^[a-z][a-z0-9+.\-]*:/i', $target)) {
                    continue; // a relative path
                }
                return false;
            }
        }
        return true;
    }

    /**
     * Does this archive actually contain an Android manifest?
     *
     * Every APK holds AndroidManifest.xml, and the ZIP central directory stores
     * entry names uncompressed, so the name is present verbatim in the bytes.
     * Read in chunks rather than loading the whole file, since an APK can be
     * large.
     */
    private static function looksLikeApk(string $path): bool
    {
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return false;
        }
        // ZIP local file header, so we know it is an archive at all.
        $magic = (string) fread($fh, 4);
        if (!in_array($magic, ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"], true)) {
            fclose($fh);
            return false;
        }
        $needle = 'AndroidManifest.xml';
        $tail   = '';
        $found  = false;
        while (!feof($fh)) {
            $chunk = (string) fread($fh, 262144);
            if ($chunk === '') {
                break;
            }
            if (str_contains($tail . $chunk, $needle)) {
                $found = true;
                break;
            }
            // Carry the overlap so a name split across two reads is still seen.
            $tail = substr($chunk, -strlen($needle));
        }
        fclose($fh);
        return $found;
    }

    /** Generate a smaller preview for the media library. Returns a relative path or ''. */
    private function makeThumbnail(string $absPath, string $mime, string $folder, string $fileName, int $width, int $height): string
    {
        if (!function_exists('imagecreatetruecolor')) {
            return '';
        }
        $target = (int) $this->cfg['thumb_width'];
        if ($width <= $target) {
            return '';
        }
        $src = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($absPath),
            'image/png'  => @imagecreatefrompng($absPath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absPath) : false,
            'image/gif'  => @imagecreatefromgif($absPath),
            default      => false,
        };
        if (!$src) {
            return '';
        }
        $newW = $target;
        $newH = max(1, (int) round($height * ($target / $width)));
        $dst  = imagecreatetruecolor($newW, $newH);
        if (in_array($mime, ['image/png', 'image/webp', 'image/gif'], true)) {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $width, $height);

        $thumbDir = $this->root . '/uploads/thumbs/' . $folder . '/' . date('Y/m');
        if (!is_dir($thumbDir) && !@mkdir($thumbDir, 0775, true) && !is_dir($thumbDir)) {
            imagedestroy($src);
            imagedestroy($dst);
            return '';
        }
        // Serve thumbnails as WebP where the build supports it — smaller and
        // universally supported by current browsers.
        $useWebp   = function_exists('imagewebp');
        $thumbName = pathinfo($fileName, PATHINFO_FILENAME) . ($useWebp ? '.webp' : '.jpg');
        $thumbAbs  = $thumbDir . '/' . $thumbName;
        $ok        = $useWebp ? @imagewebp($dst, $thumbAbs, 82) : @imagejpeg($dst, $thumbAbs, 84);

        imagedestroy($src);
        imagedestroy($dst);
        if (!$ok) {
            return '';
        }
        @chmod($thumbAbs, 0644);
        return 'uploads/thumbs/' . $folder . '/' . date('Y/m') . '/' . $thumbName;
    }

    private function errorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'The file is larger than the server allows.',
            UPLOAD_ERR_PARTIAL   => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_FILE   => 'No file was selected.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not write the file.',
            UPLOAD_ERR_EXTENSION => 'The upload was blocked by a server extension.',
            default              => 'The file could not be uploaded.',
        };
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i     = 0;
        $n     = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) {
            $n /= 1024;
            $i++;
        }
        return round($n, $i === 0 ? 0 : 1) . ' ' . $units[$i];
    }
}
