<?php
declare(strict_types=1);

final class Upload
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        'image/svg+xml' => 'svg',
    ];
    private const MAX_BYTES = 4 * 1024 * 1024;

    /**
     * Store one uploaded image under uploads/<folder>/ and return its relative path.
     * @return array{0:?string,1:?string} [path, error]
     */
    public static function image(string $field, string $folder): array
    {
        if (!isset($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        $f = $_FILES[$field];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            return [null, 'The file could not be uploaded (error ' . (int)$f['error'] . ').'];
        }
        if ($f['size'] > self::MAX_BYTES) {
            return [null, 'Images must be 4 MB or smaller.'];
        }
        if (!is_uploaded_file($f['tmp_name'])) {
            return [null, 'Invalid upload.'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = (string)$finfo->file($f['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            return [null, 'Use a JPG, PNG, WEBP, GIF or SVG image.'];
        }
        // An SVG can carry script; only accept it when it has no script or event handlers.
        if ($mime === 'image/svg+xml') {
            $svg = (string)file_get_contents($f['tmp_name'], false, null, 0, self::MAX_BYTES);
            if (preg_match('/<\s*script|on[a-z]+\s*=|javascript:/i', $svg)) {
                return [null, 'That SVG contains script and was rejected.'];
            }
        }

        $folder = preg_replace('/[^a-z0-9_\-]/', '', $folder) ?: 'site';
        $dir    = base_path('uploads/' . $folder);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
            return [null, 'The uploads folder is not writable.'];
        }

        $name = date('Ymd') . '-' . bin2hex(random_bytes(8)) . '.' . self::ALLOWED[$mime];
        if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
            return [null, 'Could not save the file. Check folder permissions.'];
        }
        @chmod($dir . '/' . $name, 0644);
        return ['uploads/' . $folder . '/' . $name, null];
    }

    /** Remove a previously stored upload. Ignores anything outside uploads/. */
    public static function remove(?string $relative): void
    {
        if (!$relative || !str_starts_with($relative, 'uploads/')) {
            return;
        }
        $full = base_path($relative);
        $real = realpath($full);
        $root = realpath(base_path('uploads'));
        if ($real && $root && str_starts_with($real, $root) && is_file($real)) {
            @unlink($real);
        }
    }
}
