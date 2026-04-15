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

            $qrText = $this->decodeQrWithPhpZbar($imageData);
            if (!$qrText) {
                $qrText = $this->decodeQrWithZxingonline($imageData);
            }

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

    private function decodeQrWithPhpZbar($imageData): ?string
    {
        try {
            $tempFile = tempnam(sys_get_temp_dir(), 'qr_');
            file_put_contents($tempFile, $imageData);

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
                        return $results[0];
                    }

                    zbar_image_destroy($image);
                    imagedestroy($tempImage);
                }
            }

            @unlink($tempFile);
            return null;
        } catch (\Throwable $e) {
            Log::debug('php-zbar no disponible o error: ' . $e->getMessage());
            return null;
        }
    }

    private function decodeQrWithZxingonline($imageData): ?string
    {
        try {
            $base64Image = base64_encode($imageData);

            $response = Http::timeout(10)->post('https://api.qrserver.com/api/read/qr', [
                'image' => 'data:image/png;base64,' . $base64Image,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data[0]['symbol'][0]['data'])) {
                    return $data[0]['symbol'][0]['data'];
                }
            }

            return $this->decodeQrWithOpencv($imageData);
        } catch (\Throwable $e) {
            Log::debug('ZXing online no disponible: ' . $e->getMessage());
            return null;
        }
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
