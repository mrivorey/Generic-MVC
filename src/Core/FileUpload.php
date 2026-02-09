<?php

namespace App\Core;

class FileUpload
{
    private static ?array $config = null;

    private const ERROR_MESSAGES = [
        UPLOAD_ERR_INI_SIZE   => 'The uploaded file exceeds the server maximum upload size.',
        UPLOAD_ERR_FORM_SIZE  => 'The uploaded file exceeds the form maximum upload size.',
        UPLOAD_ERR_PARTIAL    => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the upload.',
    ];

    public static function handle(string $fieldName, array $options = []): ?array
    {
        if (!isset($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        $file = $_FILES[$fieldName];

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = self::ERROR_MESSAGES[$file['error']] ?? 'Unknown upload error.';
            throw new \RuntimeException($message);
        }

        $config = self::loadConfig();

        // Detect MIME type server-side via finfo (not client-supplied type)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);

        // Validate MIME type
        $allowedTypes = $options['allowed_types'] ?? $config['allowed_types'];
        if (!in_array($mimeType, $allowedTypes, true)) {
            throw new \RuntimeException("File type '{$mimeType}' is not allowed.");
        }

        // Validate size
        $maxSize = $options['max_size'] ?? $config['max_size'];
        if ($file['size'] > $maxSize) {
            throw new \RuntimeException("File size exceeds maximum allowed size of {$maxSize} bytes.");
        }

        // Generate unique filename preserving original extension
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $uniqueName = bin2hex(random_bytes(16));
        if ($extension !== '') {
            $uniqueName .= '.' . strtolower($extension);
        }

        // Determine directory
        $directory = $options['directory'] ?? 'files';
        $relativePath = "uploads/{$directory}/{$uniqueName}";

        // Read the temp file and write via FileSystem
        $contents = file_get_contents($file['tmp_name']);
        if ($contents === false) {
            throw new \RuntimeException('Failed to read uploaded file.');
        }

        FileSystem::write($relativePath, $contents);

        return [
            'path' => $relativePath,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'mime_type' => $mimeType,
        ];
    }

    public static function delete(string $path): bool
    {
        if (!str_starts_with($path, 'uploads/')) {
            throw new \RuntimeException('Cannot delete files outside the uploads directory.');
        }

        try {
            FileSystem::delete($path);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    public static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    public static function reset(): void
    {
        self::$config = null;
    }

    private static function loadConfig(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        $appConfig = require dirname(__DIR__, 2) . '/config/app.php';
        $uploads = $appConfig['uploads'] ?? [];

        self::$config = [
            'allowed_types' => $uploads['allowed_types'] ?? ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'],
            'max_size' => $uploads['max_size'] ?? 5242880,
        ];

        return self::$config;
    }
}
