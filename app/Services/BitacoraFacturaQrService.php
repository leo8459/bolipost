<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Smalot\PdfParser\Parser;
use Zxing\QrReader;

class BitacoraFacturaQrService
{
    private const MAX_REMOTE_QR_ATTEMPTS = 6;
    private const QR_REMOTE_TIMEOUT_SECONDS = 3;
    private const MAX_VARIANT_SIDE = 900;
    private const MIN_VARIANT_SIDE = 700;
    private const MAX_QR_SECONDS = 35;

    public function extractFromUploadedFile(UploadedFile $file): array
    {
        $extension = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));

        if ($extension === 'pdf') {
            return $this->extractFromPdf($file);
        }

        return $this->extractFromImage($file);
    }

    public function extractFromQrText(string $qrText): array
    {
        $qrText = trim($qrText);
        if ($qrText === '') {
            return [
                'success' => false,
                'message' => 'No se recibio ningun dato del QR.',
            ];
        }

        return $this->buildExtractionResponse($qrText, 'camera');
    }

    private function extractFromPdf(UploadedFile $file): array
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($file->getRealPath());
            $text = $pdf->getText();
            $qrText = $this->extractQrCandidateFromText($text);

            if (!$qrText) {
                return [
                    'success' => false,
                    'message' => 'No se pudo encontrar el enlace o datos del QR dentro del PDF.',
                ];
            }

            return $this->buildExtractionResponse($qrText, 'pdf');
        } catch (\Throwable $e) {
            Log::warning('No se pudo procesar PDF para QR de bitacora.', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            return [
                'success' => false,
                'message' => 'No se pudo leer el PDF para extraer el QR.',
            ];
        }
    }

    private function extractFromImage(UploadedFile $file): array
    {
        $startedAt = microtime(true);

        try {
            $imageData = file_get_contents($file->getRealPath());
            if ($imageData === false || $imageData === '') {
                Log::warning('Bitacora QR: imagen vacia o no legible.', [
                    'file' => $file->getClientOriginalName(),
                ]);

                return [
                    'success' => false,
                    'message' => 'No se pudo leer la imagen subida.',
                ];
            }

            $qrText = $this->decodeQrFromImageData($imageData);
            if (!$qrText) {
                Log::warning('Bitacora QR: no se encontro QR en imagen.', [
                    'file' => $file->getClientOriginalName(),
                    'bytes' => strlen($imageData),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return [
                    'success' => false,
                    'message' => 'No se encontro un QR valido en la imagen. Revise storage/logs/laravel.log para ver los intentos realizados.',
                ];
            }

            Log::info('Bitacora QR: QR leido desde imagen.', [
                'file' => $file->getClientOriginalName(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'qr_preview' => Str::limit($qrText, 120),
            ]);

            return $this->buildExtractionResponse($qrText, 'image');
        } catch (\Throwable $e) {
            Log::warning('No se pudo procesar imagen para QR de bitacora.', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
            ]);

            return [
                'success' => false,
                'message' => 'No se pudo leer la imagen para extraer el QR.',
            ];
        }
    }

    private function buildExtractionResponse(string $qrText, string $source): array
    {
        $qrUrl = $this->normalizeQrTextToUrl($qrText);
        if (!$qrUrl) {
            return [
                'success' => false,
                'message' => 'El QR fue detectado, pero no contiene una URL o datos SIAT utilizables.',
                'qr_text' => $qrText,
            ];
        }

        $invoiceData = $this->scrapeInvoiceDataFromQrUrl($qrUrl);
        $message = $invoiceData['verified']
            ? 'QR leido y datos de factura obtenidos desde SIAT.'
            : 'QR leido, pero SIAT no devolvio el detalle. Solo se cargaron los datos incluidos en el enlace del QR.';

        return [
            'success' => true,
            'message' => $message,
            'source' => $source,
            'qr_text' => $qrText,
            'qr_url' => $qrUrl,
            'invoice_data' => $invoiceData,
        ];
    }

    private function decodeQrFromImageData(string $imageData): ?string
    {
        $startedAt = microtime(true);
        Log::info('Bitacora QR: preparando variantes de imagen.', [
            'original_bytes' => strlen($imageData),
            'zbar_available' => function_exists('zbar_image_create'),
        ]);

        $variants = $this->buildImageVariants($imageData);
        $remoteAttempts = 0;

        Log::info('Bitacora QR: iniciando lectura de imagen.', [
            'variants' => count($variants),
            'original_bytes' => strlen($imageData),
            'zbar_available' => function_exists('zbar_image_create'),
        ]);

        $priorityVariants = $this->priorityRemoteVariants($variants);

        foreach ($priorityVariants as $variant) {
            if ((microtime(true) - $startedAt) > self::MAX_QR_SECONDS) {
                Log::warning('Bitacora QR: lectura detenida por limite interno de tiempo.', [
                    'seconds' => self::MAX_QR_SECONDS,
                    'remote_attempts' => $remoteAttempts,
                    'phase' => 'local-priority',
                ]);

                $this->saveDebugVariants($variants);

                return null;
            }

            $label = (string) ($variant['label'] ?? 'sin-etiqueta');
            $bytes = (string) ($variant['data'] ?? '');
            if ($bytes === '') {
                continue;
            }

            $local = $this->decodeQrWithPhpZbar($bytes, $label);
            if ($local) {
                return $local;
            }

            $local = $this->decodeQrWithPhpQrReader($bytes, $label);
            if ($local) {
                return $local;
            }
        }

        foreach ($priorityVariants as $variant) {
            if ((microtime(true) - $startedAt) > self::MAX_QR_SECONDS) {
                Log::warning('Bitacora QR: lectura detenida por limite interno de tiempo.', [
                    'seconds' => self::MAX_QR_SECONDS,
                    'remote_attempts' => $remoteAttempts,
                    'phase' => 'remote-priority',
                ]);

                $this->saveDebugVariants($variants);

                return null;
            }

            $label = (string) ($variant['label'] ?? 'sin-etiqueta');
            $bytes = (string) ($variant['data'] ?? '');
            if ($bytes === '') {
                continue;
            }

            if ($remoteAttempts >= self::MAX_REMOTE_QR_ATTEMPTS) {
                break;
            }

            $remoteAttempts++;
            $remote = $this->decodeQrWithQrServer($bytes, $label, $remoteAttempts);
            if ($remote) {
                return $remote;
            }
        }

        foreach ($variants as $variant) {
            if ((microtime(true) - $startedAt) > self::MAX_QR_SECONDS) {
                Log::warning('Bitacora QR: lectura detenida por limite interno de tiempo.', [
                    'seconds' => self::MAX_QR_SECONDS,
                    'remote_attempts' => $remoteAttempts,
                    'phase' => 'local',
                ]);

                $this->saveDebugVariants($variants);

                return null;
            }

            $label = (string) ($variant['label'] ?? 'sin-etiqueta');
            $bytes = (string) ($variant['data'] ?? '');
            if ($bytes === '') {
                continue;
            }

            $local = $this->decodeQrWithPhpZbar($bytes, $label);
            if ($local) {
                return $local;
            }

            $local = $this->decodeQrWithPhpQrReader($bytes, $label);
            if ($local) {
                return $local;
            }
        }

        foreach ($variants as $variant) {
            if ((microtime(true) - $startedAt) > self::MAX_QR_SECONDS) {
                Log::warning('Bitacora QR: lectura detenida por limite interno de tiempo.', [
                    'seconds' => self::MAX_QR_SECONDS,
                    'remote_attempts' => $remoteAttempts,
                    'phase' => 'remote',
                ]);

                $this->saveDebugVariants($variants);

                return null;
            }

            if ($remoteAttempts >= self::MAX_REMOTE_QR_ATTEMPTS) {
                break;
            }

            $label = (string) ($variant['label'] ?? 'sin-etiqueta');
            $bytes = (string) ($variant['data'] ?? '');
            if ($bytes === '') {
                continue;
            }

            if (!Str::contains($label, ['inferior', 'abajo', 'qr'])) {
                continue;
            }

            $remoteAttempts++;
            $remote = $this->decodeQrWithQrServer($bytes, $label, $remoteAttempts);
            if ($remote) {
                return $remote;
            }
        }

        Log::warning('Bitacora QR: todos los intentos fallaron.', [
            'variants' => count($variants),
            'remote_attempts' => $remoteAttempts,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        $this->saveDebugVariants($variants);

        return null;
    }

    private function priorityRemoteVariants(array $variants): array
    {
        $priority = [];
        $fallback = [];

        foreach ($variants as $variant) {
            $label = (string) ($variant['label'] ?? '');

            if (Str::contains($label, ['completa-rotada-90', 'completa-rotada--90', 'completa-base', 'completa-gris-contraste'])) {
                $priority[] = $variant;
                continue;
            }

            if (Str::contains($label, ['qr-cuadro-medio', 'qr-zona-amplia', 'zona-qr-izquierda'])) {
                if (Str::contains($label, ['base', 'gris-contraste', 'rotada-90', 'rotada--90'])) {
                    $priority[] = $variant;
                }
                continue;
            }

            if (Str::contains($label, ['qr-cuadro', 'zona-qr-central'])) {
                if (Str::contains($label, ['base', 'gris-contraste', 'rotada-90', 'rotada--90', 'bn-145'])) {
                    $priority[] = $variant;
                }
                continue;
            }

            if (Str::contains($label, [
                'zona-qr-inferior-base',
                'zona-qr-inferior-gris-contraste',
                'zona-qr-central-base',
                'zona-qr-central-gris-contraste',
                'completa-base',
                'completa-rotada-90',
                'completa-rotada--90',
            ])) {
                $fallback[] = $variant;
            }
        }

        return array_slice(array_merge($priority, $fallback), 0, self::MAX_REMOTE_QR_ATTEMPTS);
    }

    private function decodeQrWithPhpZbar(string $imageData, string $label): ?string
    {
        try {
            if (!function_exists('zbar_image_create')) {
                return null;
            }

            $tempImage = imagecreatefromstring($imageData);
            if (!$tempImage) {
                return null;
            }

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

            $image = zbar_image_create();
            zbar_image_set_format($image, 'Y800');
            zbar_image_set_size($image, $width, $height);
            zbar_image_set_data($image, $raw);

            $scanner = zbar_decoder_create();
            zbar_decode_image($scanner, $image);
            $results = zbar_decoder_get_results($scanner);

            zbar_image_destroy($image);
            imagedestroy($tempImage);

            $result = !empty($results) ? (string) $results[0] : null;
            Log::debug('Bitacora QR: intento local zbar.', [
                'variant' => $label,
                'success' => $result !== null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::debug('Bitacora QR: php-zbar no pudo leer variante.', [
                'variant' => $label,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function decodeQrWithPhpQrReader(string $imageData, string $label): ?string
    {
        $startedAt = microtime(true);
        $tempFile = tempnam(sys_get_temp_dir(), 'bitacora_qr_');

        if ($tempFile === false) {
            return null;
        }

        $pngFile = $tempFile . '.png';

        try {
            file_put_contents($pngFile, $imageData);

            $reader = new QrReader($pngFile);
            $text = $reader->text();
            $result = is_string($text) && trim($text) !== '' ? trim($text) : null;

            Log::info('Bitacora QR: intento local php-qrcode-detector.', [
                'variant' => $label,
                'success' => $result !== null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'qr_preview' => $result ? Str::limit($result, 120) : null,
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::debug('Bitacora QR: php-qrcode-detector no pudo leer variante.', [
                'variant' => $label,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $e->getMessage(),
            ]);

            return null;
        } finally {
            @unlink($tempFile);
            @unlink($pngFile);
        }
    }

    private function decodeQrWithQrServer(string $imageData, string $label, int $attempt): ?string
    {
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(self::QR_REMOTE_TIMEOUT_SECONDS)
                ->attach('file', $imageData, $label . '.png')
                ->post('https://api.qrserver.com/v1/read-qr-code/', [
                    'outputformat' => 'json',
                ]);

            if (!$response->successful()) {
                Log::warning('Bitacora QR: lector remoto respondio con error HTTP.', [
                    'variant' => $label,
                    'attempt' => $attempt,
                    'status' => $response->status(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]);

                return null;
            }

            $data = $response->json();
            $value = data_get($data, '0.symbol.0.data');
            $error = data_get($data, '0.symbol.0.error');

            $result = is_string($value) && trim($value) !== '' ? trim($value) : null;
            Log::info('Bitacora QR: intento remoto finalizado.', [
                'variant' => $label,
                'attempt' => $attempt,
                'success' => $result !== null,
                'error' => $error,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'response' => $result !== null ? null : Str::limit(json_encode($data), 300),
            ]);

            return $result;
        } catch (\Throwable $e) {
            Log::warning('Bitacora QR: lector remoto no disponible.', [
                'variant' => $label,
                'attempt' => $attempt,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function buildImageVariants(string $imageData): array
    {
        $variants = [];
        $image = @imagecreatefromstring($imageData);

        if (!$image) {
            return [
                ['label' => 'original-no-procesable', 'data' => $imageData],
            ];
        }

        $width = imagesx($image);
        $height = imagesy($image);

        $crops = [
            ['completa', 0, 0, $width, $height],
            ['qr-cuadro-inferior', (int) round($width * 0.20), (int) round($height * 0.58), (int) round($width * 0.52), (int) round($height * 0.36)],
            ['qr-cuadro-medio-inferior', (int) round($width * 0.14), (int) round($height * 0.50), (int) round($width * 0.62), (int) round($height * 0.44)],
            ['qr-zona-amplia', 0, (int) round($height * 0.40), (int) round($width * 0.82), (int) round($height * 0.60)],
            ['zona-qr-central', (int) round($width * 0.04), (int) round($height * 0.28), (int) round($width * 0.92), (int) round($height * 0.72)],
            ['zona-qr-izquierda', 0, (int) round($height * 0.08), (int) round($width * 0.78), (int) round($height * 0.84)],
        ];

        foreach ($crops as [$cropLabel, $x, $y, $cropWidth, $cropHeight]) {
            $cropped = imagecrop($image, [
                'x' => max(0, $x),
                'y' => max(0, $y),
                'width' => max(1, min($cropWidth, $width - max(0, $x))),
                'height' => max(1, min($cropHeight, $height - max(0, $y))),
            ]);

            if (!$cropped) {
                continue;
            }

            $includeRotations = true;

            foreach ($this->renderVariantSet($cropped, $cropLabel, $includeRotations) as $variant) {
                $variants[] = $variant;
            }

            imagedestroy($cropped);
        }

        imagedestroy($image);

        return $this->uniqueBinaryVariants($variants);
    }

    private function renderVariantSet(\GdImage $image, string $prefix, bool $includeRotations = true): array
    {
        $variants = [];

        $base = $this->scaleToReadableSide($image);
        if (!$base) {
            return $variants;
        }

        $variants[] = ['label' => $prefix . '-base', 'data' => $this->imageToPngString($base)];

        $grayscale = $this->cloneImage($base);
        if ($grayscale) {
            imagefilter($grayscale, IMG_FILTER_GRAYSCALE);
            imagefilter($grayscale, IMG_FILTER_CONTRAST, -35);
            $variants[] = ['label' => $prefix . '-gris-contraste', 'data' => $this->imageToPngString($grayscale)];

            foreach ([145] as $threshold) {
                $thresholded = $this->cloneImage($grayscale);
                if (!$thresholded) {
                    continue;
                }

                $this->applyThreshold($thresholded, $threshold);
                $variants[] = ['label' => $prefix . '-bn-' . $threshold, 'data' => $this->imageToPngString($thresholded)];
                imagedestroy($thresholded);
            }

            imagedestroy($grayscale);
        }

        if ($includeRotations) {
            foreach ([90, -90] as $angle) {
                $rotated = imagerotate($base, $angle, 0);
                if (!$rotated) {
                    continue;
                }

                $variants[] = ['label' => $prefix . '-rotada-' . $angle, 'data' => $this->imageToPngString($rotated)];

                $grayscaleRotated = $this->cloneImage($rotated);
                if ($grayscaleRotated) {
                    imagefilter($grayscaleRotated, IMG_FILTER_GRAYSCALE);
                    imagefilter($grayscaleRotated, IMG_FILTER_CONTRAST, -40);
                    $variants[] = ['label' => $prefix . '-rotada-' . $angle . '-gris', 'data' => $this->imageToPngString($grayscaleRotated)];
                    imagedestroy($grayscaleRotated);
                }

                imagedestroy($rotated);
            }
        }

        imagedestroy($base);

        return array_values(array_filter($variants, fn (array $variant) => !empty($variant['data'])));
    }

    private function scaleToReadableSide(\GdImage $image): ?\GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $largestSide = max($width, $height);

        if ($largestSide > self::MAX_VARIANT_SIDE) {
            return $this->scaleToMaxSide($image, self::MAX_VARIANT_SIDE);
        }

        if ($largestSide < self::MIN_VARIANT_SIDE) {
            $scale = self::MIN_VARIANT_SIDE / max(1, $largestSide);
            $newWidth = max(1, (int) round($width * $scale));
            $newHeight = max(1, (int) round($height * $scale));

            return imagescale($image, $newWidth, $newHeight, IMG_BICUBIC_FIXED) ?: null;
        }

        return $this->cloneImage($image);
    }

    private function scaleToMaxSide(\GdImage $image, int $maxSide): ?\GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $largestSide = max($width, $height);

        if ($largestSide <= $maxSide) {
            return $this->cloneImage($image);
        }

        $scale = $maxSide / $largestSide;
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        return imagescale($image, $newWidth, $newHeight, IMG_BICUBIC_FIXED) ?: null;
    }

    private function cloneImage(\GdImage $source): ?\GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $clone = imagecreatetruecolor($width, $height);

        if (!$clone) {
            return null;
        }

        imagecopy($clone, $source, 0, 0, 0, 0, $width, $height);

        return $clone;
    }

    private function applyThreshold(\GdImage $image, int $threshold): void
    {
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
                $gray = (int) (($r + $g + $b) / 3);

                imagesetpixel($image, $x, $y, $gray >= $threshold ? $white : $black);
            }
        }
    }

    private function imageToPngString(\GdImage $image): ?string
    {
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();

        return is_string($contents) && $contents !== '' ? $contents : null;
    }

    private function uniqueBinaryVariants(array $variants): array
    {
        $unique = [];
        $hashes = [];

        foreach ($variants as $variant) {
            $data = (string) ($variant['data'] ?? '');
            if ($data === '') {
                continue;
            }

            $hash = md5($data);
            if (isset($hashes[$hash])) {
                continue;
            }

            $hashes[$hash] = true;
            $unique[] = [
                'label' => (string) ($variant['label'] ?? 'variante'),
                'data' => $data,
            ];
        }

        return $unique;
    }

    private function saveDebugVariants(array $variants): void
    {
        if (!filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOL)) {
            return;
        }

        try {
            $directory = storage_path('app/bitacora-qr-debug');
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }

            foreach (glob($directory . DIRECTORY_SEPARATOR . '*.png') ?: [] as $file) {
                @unlink($file);
            }

            foreach (array_slice($this->priorityRemoteVariants($variants), 0, 12) as $index => $variant) {
                $label = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($variant['label'] ?? 'variante'));
                file_put_contents($directory . DIRECTORY_SEPARATOR . str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) . '-' . $label . '.png', (string) ($variant['data'] ?? ''));
            }

            Log::info('Bitacora QR: variantes debug guardadas.', [
                'path' => $directory,
                'files' => count(glob($directory . DIRECTORY_SEPARATOR . '*.png') ?: []),
            ]);
        } catch (\Throwable $e) {
            Log::debug('Bitacora QR: no se pudieron guardar variantes debug.', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function extractQrCandidateFromText(string $text): ?string
    {
        $normalized = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $flattened = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
        $compact = preg_replace('/\s+/', '', $normalized) ?? $normalized;

        foreach ([$flattened, $compact] as $candidateText) {
            if (preg_match_all('~https?://[^\s<>"\']+~i', $candidateText, $matches)) {
                foreach ($matches[0] as $url) {
                    $cleanUrl = rtrim(trim($url), '.,;)');
                    if ($this->normalizeQrTextToUrl($cleanUrl)) {
                        return $cleanUrl;
                    }
                }
            }
        }

        $query = [];
        parse_str($compact, $query);
        $built = $this->buildSiatUrlFromQuery($query);
        if ($built) {
            return $built;
        }

        $nit = $this->extractPatternValue($normalized, '/\bnit(?:emisor)?\s*[:=]\s*([0-9]+)/i');
        $cuf = $this->extractPatternValue($normalized, '/\bcuf\s*[:=]\s*([A-Z0-9]+)/i');
        $numero = $this->extractPatternValue($normalized, '/\b(?:numero(?:factura)?|nro(?:factura)?)\s*[:=#-]?\s*([0-9]+)/i');

        if ($nit && $cuf && $numero) {
            return $this->buildSiatUrlFromQuery([
                'nit' => $nit,
                'cuf' => $cuf,
                'numero' => $numero,
            ]);
        }

        return null;
    }

    private function extractPatternValue(string $text, string $pattern): ?string
    {
        if (preg_match($pattern, $text, $matches)) {
            return trim((string) ($matches[1] ?? '')) ?: null;
        }

        return null;
    }

    private function normalizeQrTextToUrl(string $qrText): ?string
    {
        $value = trim(urldecode($qrText));
        if ($value === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL) && Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        if (Str::startsWith($value, 'www.')) {
            return 'https://' . $value;
        }

        $parts = parse_url($value);
        if (is_array($parts) && !empty($parts['host'])) {
            $scheme = $parts['scheme'] ?? 'https';

            return $scheme . '://' . $parts['host']
                . ($parts['path'] ?? '')
                . (!empty($parts['query']) ? '?' . $parts['query'] : '');
        }

        $query = [];
        parse_str(ltrim($value, '?'), $query);
        $built = $this->buildSiatUrlFromQuery($query);
        if ($built) {
            return $built;
        }

        return null;
    }

    private function buildSiatUrlFromQuery(array $query): ?string
    {
        $nit = $this->pickQueryValue($query, ['nitEmisor', 'nit', 'nit_emisor', 'emisor_nit']);
        $cuf = $this->pickQueryValue($query, ['cuf', 'codigo_unico_factura']);
        $numero = $this->pickQueryValue($query, ['numeroFactura', 'numero_factura', 'nro_factura', 'factura', 'numero', 'nf', 'nro']);

        if (!$nit || !$cuf || !$numero) {
            return null;
        }

        return 'https://siat.impuestos.gob.bo/consulta/QR?nit=' . urlencode($nit)
            . '&cuf=' . urlencode($cuf)
            . '&numero=' . urlencode($numero);
    }

    public function scrapeInvoiceDataFromQrUrl(string $url): array
    {
        $parts = parse_url($url);
        $query = [];
        if (!empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }

        $pick = fn (array $source, array $keys): ?string => $this->pickQueryValue($source, $keys);

        $encodedPayload = $this->extractFromEncodedQueryPayloads($query);

        $numeroFactura = $pick($query, ['numero_factura', 'nro_factura', 'nrofactura', 'factura', 'numero', 'nf', 'numeroFactura', 'nro'])
            ?: ($encodedPayload['numero_factura'] ?? null);
        $fechaEmision = $this->parseFecha(
            $pick($query, ['fecha_emision', 'fecha', 'date', 'fechaEmision'])
                ?: ($encodedPayload['fecha_emision'] ?? null)
        );
        $nombreCliente = $pick($query, ['nombre_cliente', 'cliente', 'nombre', 'razon_social_cliente', 'razonSocial'])
            ?: ($encodedPayload['nombre_cliente'] ?? null);
        $montoTotal = $this->normalizeNumber(
            $pick($query, ['monto_total', 'total', 'importe', 'monto', 'montoTotal'])
                ?: ($encodedPayload['monto_total'] ?? null)
        );
        $nitEmisor = $pick($query, ['nit_emisor', 'nit', 'emisor_nit', 'nitEmisor'])
            ?: ($encodedPayload['nit_emisor'] ?? null);
        $cuf = $pick($query, ['cuf', 'codigo_unico_factura'])
            ?: ($encodedPayload['cuf'] ?? null);
        $razonSocial = $pick($query, ['razon_social', 'nombre_estacion', 'razonSocialEmisor'])
            ?: ($encodedPayload['razon_social'] ?? null);
        $direccion = $pick($query, ['direccion', 'direccionEstacion'])
            ?: ($encodedPayload['direccion'] ?? null);
        $cantidad = $this->normalizeNumber(
            $pick($query, ['cantidad', 'litros', 'volumen'])
                ?: ($encodedPayload['cantidad'] ?? null)
        );
        $precioUnitario = $this->normalizeNumber(
            $pick($query, ['precio_unitario', 'precio', 'pu', 'precioUnitario'])
                ?: ($encodedPayload['precio_unitario'] ?? null)
        );

        $data = [
            'verified' => false,
            'numero_factura' => $numeroFactura,
            'fecha_emision' => $fechaEmision,
            'nombre_cliente' => $nombreCliente,
            'monto_total' => $montoTotal,
            'nit_emisor' => $nitEmisor,
            'cuf' => $cuf,
            'razon_social_emisor' => $razonSocial,
            'direccion_emisor' => $direccion,
            'cantidad' => $cantidad,
            'precio_unitario' => $precioUnitario,
            'details' => [],
            'qr_url' => $url,
        ];

        if ($cantidad && $precioUnitario) {
            $data['details'][] = [
                'cantidad' => $cantidad,
                'precio_unitario' => $precioUnitario,
                'subtotal' => round($cantidad * $precioUnitario, 2),
            ];
        }

        $siatFactura = $this->getFacturaSiat($nitEmisor, $cuf, $numeroFactura);
        if (!is_array($siatFactura)) {
            return $data;
        }

        $detalle = data_get($siatFactura, 'listaDetalle.0');
        $siatDetalles = data_get($siatFactura, 'detalles')
            ?? data_get($siatFactura, 'detalle')
            ?? data_get($siatFactura, 'listaDetalle')
            ?? data_get($siatFactura, 'lista_detalle')
            ?? data_get($siatFactura, 'cabecera.detalles')
            ?? [];

        $mappedDetails = [];
        if (is_array($siatDetalles)) {
            foreach ($siatDetalles as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $cantidadRow = $this->normalizeNumber((string) (data_get($row, 'cantidad') ?? data_get($row, 'cantidadProducto') ?? ''));
                $precioRow = $this->normalizeNumber((string) (data_get($row, 'precioUnitario') ?? data_get($row, 'precio_unitario') ?? data_get($row, 'precio') ?? ''));
                $subtotalRow = $this->normalizeNumber((string) (data_get($row, 'subTotal') ?? data_get($row, 'subtotal') ?? ''));

                if ($subtotalRow === null && $cantidadRow !== null && $precioRow !== null) {
                    $subtotalRow = round($cantidadRow * $precioRow, 2);
                }

                if ($cantidadRow === null && $precioRow === null && $subtotalRow === null) {
                    continue;
                }

                $mappedDetails[] = [
                    'cantidad' => $cantidadRow,
                    'precio_unitario' => $precioRow,
                    'subtotal' => $subtotalRow,
                    'descripcion' => (string) (data_get($row, 'descripcion') ?? ''),
                    'codigo_producto' => (string) (data_get($row, 'codigoProducto') ?? data_get($row, 'codigo') ?? ''),
                ];
            }
        }

        return [
            'verified' => true,
            'numero_factura' => (string) (data_get($siatFactura, 'numeroFactura') ?? data_get($siatFactura, 'cabecera.numeroFactura') ?? $numeroFactura),
            'fecha_emision' => $this->parseFecha((string) (data_get($siatFactura, 'fechaEmision') ?? data_get($siatFactura, 'cabecera.fechaEmision') ?? data_get($siatFactura, 'fecha') ?? $fechaEmision)),
            'nombre_cliente' => (string) (data_get($siatFactura, 'nombreRazonSocial') ?? data_get($siatFactura, 'nombreCliente') ?? data_get($siatFactura, 'cabecera.nombreRazonSocial') ?? $nombreCliente),
            'monto_total' => $this->normalizeNumber((string) (data_get($siatFactura, 'montoTotal') ?? data_get($siatFactura, 'cabecera.montoTotal') ?? data_get($siatFactura, 'total') ?? $montoTotal)),
            'nit_emisor' => (string) (data_get($siatFactura, 'nitEmisor') ?? data_get($siatFactura, 'cabecera.nitEmisor') ?? $nitEmisor),
            'cuf' => (string) (data_get($siatFactura, 'cuf') ?? $cuf),
            'razon_social_emisor' => (string) (data_get($siatFactura, 'razonSocialEmisor') ?? data_get($siatFactura, 'cabecera.razonSocialEmisor') ?? $razonSocial),
            'direccion_emisor' => (string) (data_get($siatFactura, 'direccion') ?? data_get($siatFactura, 'cabecera.direccion') ?? $direccion),
            'cantidad' => $this->normalizeNumber((string) (data_get($detalle, 'cantidad') ?? $cantidad)),
            'precio_unitario' => $this->normalizeNumber((string) (data_get($detalle, 'precioUnitario') ?? $precioUnitario)),
            'details' => $mappedDetails,
            'qr_url' => $url,
            'siat_payload' => $siatFactura,
        ];
    }

    private function getFacturaSiat(?string $nitEmisor, ?string $cuf, ?string $numeroFactura): ?array
    {
        $startedAt = microtime(true);
        $nit = $nitEmisor ? preg_replace('/\D+/', '', $nitEmisor) : null;
        $numero = $numeroFactura ? preg_replace('/\D+/', '', $numeroFactura) : null;
        $cuf = $cuf ? strtoupper(trim($cuf)) : null;

        if (!$nit || !$cuf || !$numero) {
            Log::info('SIAT REST omitido para bitacora: faltan datos del QR.', [
                'nit_present' => (bool) $nit,
                'cuf_present' => (bool) $cuf,
                'numero_present' => (bool) $numero,
            ]);

            return null;
        }

        $payload = [
            'nitEmisor' => $nit,
            'cuf' => $cuf,
            'numeroFactura' => $numero,
        ];

        try {
            $verifySsl = filter_var(env('SIAT_VERIFY_SSL', false), FILTER_VALIDATE_BOOL);

            $response = Http::timeout(20)
                ->retry(2, 350)
                ->withOptions(['verify' => $verifySsl])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json, text/plain, */*',
                    'Origin' => 'https://siat.impuestos.gob.bo',
                    'Referer' => 'https://siat.impuestos.gob.bo/',
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/123.0.0.0 Safari/537.36',
                ])
                ->put('https://siatrest.impuestos.gob.bo/sre-sfe-shared-v2-rest/consulta/factura', $payload);

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            if (!$response->ok()) {
                Log::warning('SIAT REST no respondio correctamente para bitacora.', [
                    'status' => $response->status(),
                    'payload' => $payload,
                    'body' => Str::limit($response->body(), 500),
                    'duration_ms' => $durationMs,
                ]);

                return null;
            }

            $json = json_decode($response->body(), true);
            if (!is_array($json)) {
                Log::warning('SIAT REST devolvio una respuesta no JSON para bitacora.', [
                    'status' => $response->status(),
                    'payload' => $payload,
                    'body' => Str::limit($response->body(), 500),
                    'duration_ms' => $durationMs,
                ]);

                return null;
            }

            Log::info('SIAT REST respondio para bitacora.', [
                'status' => $response->status(),
                'payload' => $payload,
                'transaccion' => $json['transaccion'] ?? null,
                'has_objeto' => is_array(data_get($json, 'objeto')) || is_array(data_get($json, 'data.objeto')),
                'duration_ms' => $durationMs,
            ]);

            if (array_key_exists('transaccion', $json) && $json['transaccion'] === false) {
                Log::warning('SIAT REST no valido la factura para bitacora.', [
                    'payload' => $payload,
                    'mensajes' => $json['mensajes'] ?? $json['mensajesList'] ?? null,
                    'body' => Str::limit(json_encode($json), 800),
                    'duration_ms' => $durationMs,
                ]);

                return null;
            }

            $objeto = data_get($json, 'objeto');
            if (is_array($objeto)) {
                return $objeto;
            }

            $fallback = data_get($json, 'data.objeto');

            if (is_array($fallback)) {
                return $fallback;
            }

            Log::warning('SIAT REST respondio sin objeto de factura para bitacora.', [
                'payload' => $payload,
                'body' => Str::limit(json_encode($json), 800),
                'duration_ms' => $durationMs,
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('Error consultando SIAT para bitacora.', [
                'payload' => $payload,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            return null;
        }
    }

    private function pickQueryValue(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source) && trim((string) $source[$key]) !== '') {
                return trim((string) $source[$key]);
            }
        }

        return null;
    }

    private function parseFecha(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $raw = trim($value);
        $formats = [
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'Y-m-d\TH:i:s.v',
            'Y-m-d\TH:i:s',
            'Y-m-d H:i:s',
            'Y-m-d H:i',
            \DateTimeInterface::ATOM,
        ];

        foreach ($formats as $format) {
            try {
                return \Carbon\Carbon::createFromFormat($format, $raw)->format('Y-m-d H:i');
            } catch (\Throwable $e) {
            }
        }

        try {
            return \Carbon\Carbon::parse(str_replace('/', '-', $raw))->format('Y-m-d H:i');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function normalizeNumber(?string $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $clean = preg_replace('/[^0-9,\.\-]/', '', $value);
        if ($clean === '') {
            return null;
        }

        $lastComma = strrpos($clean, ',');
        $lastDot = strrpos($clean, '.');

        if ($lastComma !== false && $lastDot !== false) {
            if ($lastComma > $lastDot) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($lastComma !== false) {
            $clean = str_replace(',', '.', $clean);
        }

        return is_numeric($clean) ? (float) $clean : null;
    }

    private function extractFromEncodedQueryPayloads(array $query): array
    {
        $result = [
            'numero_factura' => null,
            'fecha_emision' => null,
            'nombre_cliente' => null,
            'monto_total' => null,
            'nit_emisor' => null,
            'cuf' => null,
            'razon_social' => null,
            'direccion' => null,
            'cantidad' => null,
            'precio_unitario' => null,
        ];

        $mergeKnownKeys = function (array $payload) use (&$result) {
            $result['numero_factura'] = $result['numero_factura'] ?: $this->pickQueryValue($payload, ['numero_factura', 'nro_factura', 'numeroFactura', 'factura', 'numero', 'nf']);
            $result['fecha_emision'] = $result['fecha_emision'] ?: $this->pickQueryValue($payload, ['fecha_emision', 'fecha', 'fechaEmision']);
            $result['nombre_cliente'] = $result['nombre_cliente'] ?: $this->pickQueryValue($payload, ['nombre_cliente', 'cliente', 'nombre', 'razonSocial']);
            $result['monto_total'] = $result['monto_total'] ?: $this->pickQueryValue($payload, ['monto_total', 'montoTotal', 'total', 'importe']);
            $result['nit_emisor'] = $result['nit_emisor'] ?: $this->pickQueryValue($payload, ['nit_emisor', 'nit', 'nitEmisor']);
            $result['cuf'] = $result['cuf'] ?: $this->pickQueryValue($payload, ['cuf', 'codigo_unico_factura']);
            $result['razon_social'] = $result['razon_social'] ?: $this->pickQueryValue($payload, ['razon_social', 'razonSocial', 'nombre_estacion']);
            $result['direccion'] = $result['direccion'] ?: $this->pickQueryValue($payload, ['direccion']);
            $result['cantidad'] = $result['cantidad'] ?: $this->pickQueryValue($payload, ['cantidad', 'litros', 'volumen']);
            $result['precio_unitario'] = $result['precio_unitario'] ?: $this->pickQueryValue($payload, ['precio_unitario', 'precioUnitario', 'precio', 'pu']);
        };

        foreach ($query as $value) {
            $raw = trim((string) $value);
            if ($raw === '' || strlen($raw) < 16) {
                continue;
            }

            $decoded = urldecode($raw);

            if (str_contains($decoded, '.') && substr_count($decoded, '.') === 2) {
                $parts = explode('.', $decoded);
                $payload = $parts[1] ?? '';
                $payload = str_replace(['-', '_'], ['+', '/'], $payload);
                $payload .= str_repeat('=', (4 - strlen($payload) % 4) % 4);
                $json = base64_decode($payload, true);

                if ($json) {
                    $arr = json_decode($json, true);
                    if (is_array($arr)) {
                        $mergeKnownKeys($arr);
                    }
                }
            }

            $b64 = base64_decode($decoded, true);
            if ($b64 !== false && $b64 !== '') {
                $arr = json_decode($b64, true);
                if (is_array($arr)) {
                    $mergeKnownKeys($arr);
                } else {
                    $pairs = [];
                    parse_str($b64, $pairs);
                    if (!empty($pairs)) {
                        $mergeKnownKeys($pairs);
                    }
                }
            }

            $pairs = [];
            parse_str($decoded, $pairs);
            if (!empty($pairs)) {
                $mergeKnownKeys($pairs);
            }
        }

        return $result;
    }
}
