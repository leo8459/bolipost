<?php

namespace App\Support;

class StoredImage
{
    private const MAX_WIDTH = 1600;
    private const MAX_HEIGHT = 1600;
    private const INITIAL_JPEG_QUALITY = 78;
    private const MIN_JPEG_QUALITY = 58;
    private const TARGET_BINARY_BYTES = 450000;

    public static function fromUploadedFile($file): ?string
    {
        if (! $file) {
            return null;
        }

        $path = method_exists($file, 'getRealPath') ? $file->getRealPath() : null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        $optimized = self::optimizeImageBinary($contents);
        if ($optimized !== null) {
            return 'data:image/jpeg;base64,' . base64_encode($optimized);
        }

        $mimeType = trim((string) (method_exists($file, 'getMimeType') ? $file->getMimeType() : ''));
        $mimeType = $mimeType !== '' ? $mimeType : 'image/jpeg';

        return 'data:' . $mimeType . ';base64,' . base64_encode($contents);
    }

    private static function optimizeImageBinary(string $contents): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $source = @imagecreatefromstring($contents);
        if (! $source) {
            return null;
        }

        $source = self::normalizeOrientation($source, $contents);
        $width = imagesx($source);
        $height = imagesy($source);

        if ($width <= 0 || $height <= 0) {
            imagedestroy($source);
            return null;
        }

        $scale = min(1, self::MAX_WIDTH / $width, self::MAX_HEIGHT / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($targetWidth, $targetHeight);
        if (! $target) {
            imagedestroy($source);
            return null;
        }

        $white = imagecolorallocate($target, 255, 255, 255);
        imagefilledrectangle($target, 0, 0, $targetWidth, $targetHeight, $white);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $quality = self::INITIAL_JPEG_QUALITY;
        $optimized = self::encodeJpeg($target, $quality);

        while (
            $optimized !== null
            && strlen($optimized) > self::TARGET_BINARY_BYTES
            && $quality > self::MIN_JPEG_QUALITY
        ) {
            $quality -= 5;
            $optimized = self::encodeJpeg($target, $quality);
        }

        imagedestroy($target);
        imagedestroy($source);

        return $optimized;
    }

    private static function encodeJpeg($image, int $quality): ?string
    {
        ob_start();
        $ok = imagejpeg($image, null, $quality);
        $binary = ob_get_clean();

        return $ok && is_string($binary) && $binary !== '' ? $binary : null;
    }

    private static function normalizeOrientation($image, string $contents)
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $temp = tmpfile();
        if (! $temp) {
            return $image;
        }

        $meta = stream_get_meta_data($temp);
        fwrite($temp, $contents);

        $orientation = null;
        if (! empty($meta['uri'])) {
            $exif = @exif_read_data($meta['uri']);
            $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 0) : null;
        }

        fclose($temp);

        return match ($orientation) {
            3 => imagerotate($image, 180, 0) ?: $image,
            6 => imagerotate($image, -90, 0) ?: $image,
            8 => imagerotate($image, 90, 0) ?: $image,
            default => $image,
        };
    }

    public static function url(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^data:image\/[a-z0-9.+-]+;base64,/i', $value) === 1) {
            return $value;
        }

        if (preg_match('/^https?:\/\//i', $value) === 1) {
            return $value;
        }

        if (self::looksLikeRawBase64($value)) {
            return 'data:image/jpeg;base64,' . $value;
        }

        return asset('storage/' . ltrim($value, '/'));
    }

    public static function isStoragePath(?string $value): bool
    {
        $value = trim((string) $value);

        return $value !== ''
            && preg_match('/^data:image\/[a-z0-9.+-]+;base64,/i', $value) !== 1
            && preg_match('/^https?:\/\//i', $value) !== 1
            && ! self::looksLikeRawBase64($value);
    }

    private static function looksLikeRawBase64(string $value): bool
    {
        if (strlen($value) < 128 || str_contains($value, '\\') || str_contains($value, '.')) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9+\/=\r\n]+$/', $value) === 1
            && base64_decode($value, true) !== false;
    }
}
