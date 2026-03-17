<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Browsershot\Browsershot;

/**
 * Utilidad para decodificar QR desde imágenes
 * Proporciona una API backend para procesar QR cuando el cliente-side falla
 */
class QrDecoderController extends Controller
{
    /**
     * Decodificar QR desde una imagen subida
     * 
     * POST /api/qr/decode-from-image
     * 
     * Parámetros:
     * - image: archivo de imagen (base64 o multipart)
     * 
     * Retorna:
     * {
     *   "success": true,
     *   "data": {
     *     "qr_text": "https://...",
     *     "message": "QR decodificado exitosamente"
     *   }
     * }
     */
    public function decodeFromImage(Request $request)
    {
        // Validar entrada
        if (!$request->hasFile('image') && !$request->has('image_base64')) {
            return response()->json([
                'success' => false,
                'message' => 'Debe proporcionar una imagen (image o image_base64)',
            ], 422);
        }

        try {
            // Obtener la imagen
            $imageData = null;
            $imagePath = null;

            if ($request->hasFile('image')) {
                // Archivo multipart/form-data
                $file = $request->file('image');
                $imagePath = $file->getRealPath();
                $imageData = file_get_contents($imagePath);
            } elseif ($request->has('image_base64')) {
                // Base64 encoded
                $base64String = $request->input('image_base64');
                
                // Remover data URI scheme si existe
                if (Str::startsWith($base64String, 'data:')) {
                    $base64String = explode(',', $base64String)[1] ?? $base64String;
                }
                
                $imageData = base64_decode($base64String, true);
                if ($imageData === false) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Base64 inválido',
                    ], 422);
                }
            }

            if (!$imageData) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo procesar la imagen',
                ], 422);
            }

            // Intentar decodificar con php-zbar si está disponible
            $qrText = $this->decodeQrWithPhpZbar($imageData);
            
            // Si php-zbar no funciona, intentar con OpenCV o ZXing online
            if (!$qrText) {
                $qrText = $this->decodeQrWithZxingonline($imageData);
            }

            if (!$qrText) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró un QR válido en la imagen. Intenta con otra imagen más clara.',
                ], 422);
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

    /**
     * Decodificar QR usando php-zbar
     * Requiere: pecl install zbar
     */
    private function decodeQrWithPhpZbar($imageData): ?string
    {
        try {
            // Guardar imagen temporalmente
            $tempFile = tempnam(sys_get_temp_dir(), 'qr_');
            file_put_contents($tempFile, $imageData);

            // Si php-zbar está disponible
            if (function_exists('zbar_image_create')) {
                $image = zbar_image_create();
                zbar_image_set_format($image, 'Y800');
                
                $tempImage = imagecreatefromstring($imageData);
                if ($tempImage) {
                    $width = imagesx($tempImage);
                    $height = imagesy($tempImage);
                    
                    // Convertir a escala de grises
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

                    // Obtener resultados
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

    /**
     * Decodificar QR usando API online ZXing
     * Alternativa cuando php-zbar no está disponible
     */
    private function decodeQrWithZxingonline($imageData): ?string
    {
        try {
            // Convertir a base64 para envío
            $base64Image = base64_encode($imageData);

            // Usar el servidor de ZXing online
            $response = Http::timeout(10)->post('https://api.qrserver.com/api/read/qr', [
                'image' => 'data:image/png;base64,' . $base64Image,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data[0]['symbol'][0]['data'])) {
                    return $data[0]['symbol'][0]['data'];
                }
            }

            // Alternativa: OpenCV si está disponible en el servidor
            return $this->decodeQrWithOpencv($imageData);

        } catch (\Throwable $e) {
            Log::debug('ZXing online no disponible: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Decodificar QR usando OpenCV (si está instalado)
     */
    private function decodeQrWithOpencv($imageData): ?string
    {
        try {
            // Si OpenCV con PHP está instalado
            if (extension_loaded('opencv')) {
                // Implementar lógica con OpenCV
                return null;
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}





