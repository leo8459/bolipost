<?php

namespace App\Services;

use App\Models\ExternalApiToken;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ApiManualDocxService
{
    public function generate(?ExternalApiToken $token = null): string
    {
        $catalog = (array) config('external_apis.catalog', []);
        $abilities = $token
            ? array_values(array_filter((array) $token->abilities, fn (string $ability): bool => isset($catalog[$ability])))
            : array_keys($catalog);

        $directory = storage_path('app/tmp/api-manuales');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/manual-api-'.Str::uuid().'.docx';

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo generar el manual Word.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRelationships());
        $zip->addFromString('docProps/core.xml', $this->coreProperties($token));
        $zip->addFromString('docProps/app.xml', $this->appProperties());
        $zip->addFromString('word/document.xml', $this->documentXml($catalog, $abilities, $token));
        $zip->addFromString('word/styles.xml', $this->stylesXml());
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelationships());
        $zip->close();

        return $path;
    }

    private function documentXml(array $catalog, array $abilities, ?ExternalApiToken $token): string
    {
        $baseUrl = rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/');
        $body = $this->paragraph('Manual de integracion API', 'ApiTitle');
        $body .= $this->paragraph('TrackingBO - Correos de Bolivia', 'Subtitle');
        $body .= $this->paragraph(
            $token
                ? 'Credencial: '.$token->name.' | Generado: '.now()->format('d/m/Y H:i')
                : 'Catalogo completo de APIs | Generado: '.now()->format('d/m/Y H:i'),
            'Metadata'
        );
        $body .= $this->paragraph('Autorizacion', 'Heading1');
        $body .= $this->paragraph('En Postman seleccione Authorization > Bearer Token y pegue el JWT generado en Credenciales API.');
        $body .= $this->codeParagraph("Authorization: Bearer TOKEN_JWT_DE_LA_INTEGRACION\nAccept: application/json\nContent-Type: application/json");

        foreach ($abilities as $ability) {
            $api = $catalog[$ability];
            $body .= $this->paragraph((string) $api['name'], 'Heading1');
            $body .= $this->paragraph((string) $api['description']);
            $body .= $this->paragraph('Permiso: '.$ability, 'Metadata');

            foreach ((array) $api['endpoints'] as $endpoint) {
                $method = strtoupper((string) $endpoint['method']);
                $path = (string) $endpoint['path'];
                $query = (string) ($endpoint['example'] ?? '');
                $url = $baseUrl.$path.$query;
                $body .= $this->paragraph($method.' '.$path, 'Heading2');
                $body .= $this->paragraph('URL para Postman');
                $body .= $this->codeParagraph($url);
                $body .= $this->paragraph('Cabeceras');
                $body .= $this->codeParagraph("Authorization: Bearer TOKEN_JWT_DE_LA_INTEGRACION\nAccept: application/json\nContent-Type: application/json");

                if (! empty($endpoint['body'])) {
                    $body .= $this->paragraph('Body > raw > JSON', 'Heading3');
                    $body .= $this->codeParagraph($this->json($endpoint['body']));
                } else {
                    $body .= $this->paragraph('Esta solicitud no requiere cuerpo JSON.', 'Metadata');
                }

                if (! empty($endpoint['response'])) {
                    $body .= $this->paragraph('Respuesta de ejemplo', 'Heading3');
                    $body .= $this->codeParagraph($this->json($endpoint['response']));
                }

            }
        }

        $body .= $this->paragraph('Notas de seguridad', 'Heading1');
        $body .= $this->paragraph('No comparta el JWT en capturas, repositorios o mensajes. Si se expone, regenere o desactive inmediatamente la credencial desde el panel.');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$body
            .'<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    private function paragraph(string $text, string $style = 'Normal'): string
    {
        return '<w:p><w:pPr><w:pStyle w:val="'.$this->xml($style).'"/></w:pPr>'.$this->runs($text).'</w:p>';
    }

    private function codeParagraph(string $text): string
    {
        return '<w:p><w:pPr><w:pStyle w:val="Code"/><w:shd w:val="clear" w:color="auto" w:fill="F2F4F7"/></w:pPr>'.$this->runs($text, true).'</w:p>';
    }

    private function runs(string $text, bool $code = false): string
    {
        $lines = preg_split('/\R/u', $text) ?: [''];
        $runs = [];

        foreach ($lines as $index => $line) {
            if ($index > 0) {
                $runs[] = '<w:r><w:br/></w:r>';
            }
            $rPr = $code ? '<w:rPr><w:rFonts w:ascii="Consolas" w:hAnsi="Consolas"/><w:sz w:val="18"/></w:rPr>' : '';
            $runs[] = '<w:r>'.$rPr.'<w:t xml:space="preserve">'.$this->xml($line).'</w:t></w:r>';
        }

        return implode('', $runs);
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:sz w:val="22"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="300" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            .$this->style('Normal', 'Normal', 22, '000000', false, 0, 120)
            .$this->style('ApiTitle', 'Titulo API', 40, '173B6C', true, 0, 100)
            .$this->style('Subtitle', 'Subtitulo', 26, '20539A', false, 0, 160)
            .$this->style('Metadata', 'Metadatos', 18, '667085', false, 0, 100)
            .$this->style('Heading1', 'Titulo 1', 32, '2E74B5', true, 360, 200, 1)
            .$this->style('Heading2', 'Titulo 2', 26, '2E74B5', true, 280, 140, 2)
            .$this->style('Heading3', 'Titulo 3', 24, '1F4D78', true, 200, 100, 3)
            .$this->style('Code', 'Codigo', 18, '172033', false, 80, 120)
            .'</w:styles>';
    }

    private function style(string $id, string $name, int $size, string $color, bool $bold, int $before, int $after, ?int $outline = null): string
    {
        $outlineXml = $outline !== null ? '<w:outlineLvl w:val="'.($outline - 1).'"/>' : '';

        return '<w:style w:type="paragraph" w:styleId="'.$id.'"><w:name w:val="'.$this->xml($name).'"/>'
            .'<w:pPr><w:spacing w:before="'.$before.'" w:after="'.$after.'" w:line="300" w:lineRule="auto"/>'.$outlineXml.'</w:pPr>'
            .'<w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri"/><w:color w:val="'.$color.'"/><w:sz w:val="'.$size.'"/>'.($bold ? '<w:b/>' : '').'</w:rPr></w:style>';
    }

    private function json(mixed $value, bool $pretty = true): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | ($pretty ? JSON_PRETTY_PRINT : 0));
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/></Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>';
    }

    private function documentRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }

    private function coreProperties(?ExternalApiToken $token): string
    {
        $title = $token ? 'Manual API - '.$token->name : 'Manual completo de APIs TrackingBO';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:title>'.$this->xml($title).'</dc:title><dc:creator>TrackingBO</dc:creator><cp:lastModifiedBy>TrackingBO</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">'.now()->utc()->format('Y-m-d\TH:i:s\Z').'</dcterms:created></cp:coreProperties>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>TrackingBO</Application></Properties>';
    }
}
