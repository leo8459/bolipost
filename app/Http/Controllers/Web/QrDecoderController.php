<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class QrDecoderController extends Controller
{
    public function decodeFromImage(Request $request)
    {
        if (!$request->hasFile('image') && !$request->has('image_base64')) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar una imagen (image o image_base64)',
            ], 422);
        }

        try {
            $imageData = null;

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $imageData = file_get_contents($file->getRealPath());
            } elseif ($request->has('image_base64')) {
                $base64String = (string) $request->input('image_base64');

                if (Str::startsWith($base64String, 'data:')) {
                    $base64String = explode(',', $base64String)[1] ?? $base64String;
                }

                $imageData = base64_decode($base64String, true);
                if ($imageData === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Base64 invalido',
                    ], 422);
                }
            }

            if (!$imageData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo procesar la imagen',
                ], 422);
            }

            $qrText = $this->decodeQrFromCandidates($imageData);

            if (!$qrText) {
                return response()->json([
                    'success' => false,
                    'data' => [
                        'qr_text' => null,
                        'message' => 'No se encontro un QR valido en la imagen. Intenta con otra imagen mas clara.',
                    ],
                    'message' => 'No se encontro un QR valido en la imagen. Intenta con otra imagen mas clara.',
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'qr_text' => $qrText,
                    'message' => 'QR decodificado exitosamente',
                ],
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error decodificando QR: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la imagen: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function decodeQrFromCandidates(string $imageData): ?string
    {
        foreach ($this->buildQrDecodeCandidates($imageData) as $candidate) {
            $qrText = $this->decodeQrWithPhpZbar($candidate);
            if ($qrText) {
                return $qrText;
            }

            $qrText = $this->decodeQrWithZxingonline($candidate);
            if ($qrText) {
                return $qrText;
            }
        }

        return null;
    }

    /**
     * Genera varias versiones de la misma imagen para aumentar la tasa de
     * lectura cuando la foto viene inclinada, oscura o con poco contraste.
     *
     * @return array<int, string>
     */
    private function buildQrDecodeCandidates(string $imageData): array
    {
        $candidates = [$imageData];

        if (!function_exists('imagecreatefromstring')) {
            return $candidates;
        }

        try {
            $baseImage = @imagecreatefromstring($imageData);
            if (!$baseImage) {
                return $candidates;
            }

            $width = imagesx($baseImage);
            $height = imagesy($baseImage);
            if ($width <= 0 || $height <= 0) {
                imagedestroy($baseImage);
                return $candidates;
            }

            $normalized = $this->fitImageWithin($baseImage, 1000);
            $workingImage = $normalized ?: $baseImage;

            foreach ([
                $this->prepareQrFriendlyImage($workingImage, 'grayscale'),
                $this->prepareQrFriendlyImage($workingImage, 'contrast'),
                $this->prepareQrFriendlyImage($workingImage, 'binary'),
                $this->cropQrFocusedRegion($workingImage, 0.10, 0.28, 0.80, 0.60),
                $this->cropQrFocusedRegion($workingImage, 0.15, 0.36, 0.70, 0.52),
            ] as $variant) {
                if ($variant) {
                    $candidates[] = $this->imageToPngData($variant);
                }
            }

            if ($normalized && function_exists('imagedestroy')) {
                imagedestroy($normalized);
            }
            imagedestroy($baseImage);
        } catch (\Throwable $e) {
            Log::debug('No se pudieron generar variantes de imagen QR: ' . $e->getMessage());
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function fitImageWithin($image, int $maxDimension)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $largestSide = max($width, $height);
        if ($largestSide <= $maxDimension) {
            return null;
        }

        $scale = $maxDimension / $largestSide;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $scaled = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $scaled ?: null;
    }

    private function rotateImage($image, int $degrees)
    {
        if (!function_exists('imagerotate')) {
            return null;
        }

        $rotated = @imagerotate($image, $degrees, 0);
        return $rotated ?: null;
    }

    private function prepareQrFriendlyImage($image, string $mode)
    {
        if (!function_exists('imagecreatetruecolor')) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $scaledSource = null;
        $maxDimension = 1000;
        if (max($width, $height) > $maxDimension) {
            $scaled = $this->fitImageWithin($image, $maxDimension);
            if ($scaled) {
                $scaledSource = $scaled;
                $image = $scaled;
                $width = imagesx($image);
                $height = imagesy($image);
            }
        }

        $copy = imagecreatetruecolor($width, $height);
        imagecopy($copy, $image, 0, 0, 0, 0, $width, $height);
        if ($scaledSource && function_exists('imagedestroy')) {
            imagedestroy($scaledSource);
        }

        imagefilter($copy, IMG_FILTER_GRAYSCALE);

        if ($mode === 'contrast') {
            imagefilter($copy, IMG_FILTER_CONTRAST, -25);
            return $copy ?: null;
        }

        if ($mode === 'binary') {
            imagefilter($copy, IMG_FILTER_CONTRAST, -25);
            imagefilter($copy, IMG_FILTER_BRIGHTNESS, 8);
            $this->applyBinaryThreshold($copy);
            return $copy ?: null;
        }

        return $copy ?: null;
    }

    private function cropQrFocusedRegion($image, float $leftRatio, float $topRatio, float $widthRatio, float $heightRatio)
    {
        if (!function_exists('imagecrop')) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        if ($width <= 0 || $height <= 0) {
            return null;
        }

        $left = max(0, (int) round($width * $leftRatio));
        $top = max(0, (int) round($height * $topRatio));
        $cropWidth = max(1, (int) round($width * $widthRatio));
        $cropHeight = max(1, (int) round($height * $heightRatio));

        if ($left + $cropWidth > $width) {
            $cropWidth = max(1, $width - $left);
        }

        if ($top + $cropHeight > $height) {
            $cropHeight = max(1, $height - $top);
        }

        $cropped = @imagecrop($image, [
            'x' => $left,
            'y' => $top,
            'width' => $cropWidth,
            'height' => $cropHeight,
        ]);

        return $cropped ?: null;
    }

    private function applyBinaryThreshold($image): void
    {
        if (!function_exists('imagecolorat') || !function_exists('imagesx')) {
            return;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                $gray = (int) round(($r + $g + $b) / 3);
                imagesetpixel($image, $x, $y, $gray > 140 ? $white : $black);
            }
        }
    }

    private function imageToPngData($image): ?string
    {
        if (!$image || !function_exists('ob_start')) {
            return null;
        }

        try {
            ob_start();
            imagepng($image);
            $png = ob_get_clean();

            if (function_exists('imagedestroy')) {
                imagedestroy($image);
            }

            if (!$png) {
                return null;
            }

            return $png;
        } catch (\Throwable $e) {
            if (function_exists('imagedestroy')) {
                @imagedestroy($image);
            }

            return null;
        }
    }

    private function decodeQrWithPhpZbar($imageData): ?string
    {
        try {
            if (function_exists('zbar_image_create')) {
                $image = zbar_image_create();
                zbar_image_set_format($image, 'Y800');

                $tempImage = imagecreatefromstring($imageData);
                if ($tempImage) {
                    $width = imagesx($tempImage);
                    $height = imagesy($tempImage);

                    $raw = '';
                    for ($y = 0; $y < $height; $y++) {
                        for ($x = 0; $x < $width; $x++) {
                            $rgb = imagecolorat($tempImage, $x, $y);
                            $r = ($rgb >> 16) & 0xFF;
                            $g = ($rgb >> 8) & 0xFF;
                            $b = $rgb & 0xFF;
                            $gray = (int) (0.299 * $r + 0.587 * $g + 0.114 * $b);
                            $raw .= chr($gray);
                        }
                    }

                    zbar_image_set_size($image, $width, $height);
                    zbar_image_set_data($image, $raw);

                    $scanner = zbar_decoder_create();
                    zbar_decode_image($scanner, $image);

                    $results = zbar_decoder_get_results($scanner);
                    if (!empty($results)) {
                        if (function_exists('zbar_image_destroy')) {
                            zbar_image_destroy($image);
                        }
                        imagedestroy($tempImage);
                        return $results[0];
                    }

                    if (function_exists('zbar_image_destroy')) {
                        zbar_image_destroy($image);
                    }
                    imagedestroy($tempImage);
                }
            }
            return null;
        } catch (\Throwable $e) {
            Log::debug('php-zbar no disponible o error: ' . $e->getMessage());
            return null;
        }
    }

    private function decodeQrWithZxingonline($imageData): ?string
    {
        try {
            $uploadData = $this->prepareQrServerUploadData($imageData);
            if (!$uploadData) {
                return $this->decodeQrWithOpencv($imageData);
            }

            $request = Http::timeout(15);
            if (app()->environment('local')) {
                $request = $request->withoutVerifying();
            }

            $response = $request
                ->attach('file', $uploadData, 'qr.png')
                ->post('https://api.qrserver.com/v1/read-qr-code/', [
                    'outputformat' => 'json',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $decoded = trim((string) data_get($data, '0.symbol.0.data', ''));
                if ($decoded !== '') {
                    return $decoded;
                }

                $error = trim((string) data_get($data, '0.symbol.0.error', ''));
                if ($error !== '') {
                    Log::debug('QR Server no pudo decodificar la imagen: ' . $error);
                }
            } else {
                Log::debug('QR Server respondio con error HTTP.', [
                    'status' => $response->status(),
                    'body' => mb_substr($response->body(), 0, 500),
                ]);
            }

            return $this->decodeQrWithOpencv($imageData);
        } catch (\Throwable $e) {
            Log::debug('ZXing online no disponible: ' . $e->getMessage());
            return null;
        }
    }

    private function prepareQrServerUploadData(string $imageData): ?string
    {
        $maxBytes = 900 * 1024;
        if (strlen($imageData) <= $maxBytes) {
            return $imageData;
        }

        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($imageData);
        if (!$image) {
            return null;
        }

        $scaled = $this->fitImageWithin($image, 900) ?: $image;

        try {
            ob_start();
            imagejpeg($scaled, null, 82);
            $jpg = ob_get_clean();
        } finally {
            if ($scaled !== $image && function_exists('imagedestroy')) {
                imagedestroy($scaled);
            }
            if (function_exists('imagedestroy')) {
                imagedestroy($image);
            }
        }

        if (!$jpg || strlen($jpg) > 1048576) {
            return null;
        }

        return $jpg;
    }

    private function decodeQrWithOpencv($imageData): ?string
    {
        try {
            if (extension_loaded('opencv')) {
                return null;
            }

            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
