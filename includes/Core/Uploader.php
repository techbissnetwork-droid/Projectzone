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
    public function store(array $file, string $folder = 'general'): array
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
        $allowed = $this->cfg['allowed_mime'];
        if (!isset($allowed[$mime])) {
            return $fail('That file type is not allowed. Accepted types: ' . implode(', ', array_values($allowed)) . '.');
        }
        $ext = (string) $allowed[$mime];

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

        if ($mime === 'image/svg+xml') {
            $svg = (string) file_get_contents($tmp);
            if (preg_match('/<script|javascript:|on\w+\s*=|<foreignObject|<!ENTITY/i', $svg)) {
                return $fail('That SVG contains scripting and was rejected.');
            }
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
