<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\ApplyBrowserRestrictions;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

class ApplyBrowserRestrictionsTest extends TestCase
{
    public function test_adds_restrictions_before_page_scripts_and_preserves_the_document(): void
    {
        $html = '<!DOCTYPE html><html lang="es"><head><title>Bolipost</title></head><body>Contenido</body></html>';
        $response = new Response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Length' => strlen($html),
            'ETag' => 'previous-content',
        ]);

        $result = (new ApplyBrowserRestrictions)->handle(Request::create('/dashboard'), fn () => $response);

        $this->assertStringContainsString('<meta name="google" content="notranslate">', $result->getContent());
        $this->assertStringContainsString('/js/browser-restrictions.js?v=', $result->getContent());
        $this->assertStringEndsWith('<title>Bolipost</title></head><body>Contenido</body></html>', $result->getContent());
        $this->assertFalse($result->headers->has('Content-Length'));
        $this->assertFalse($result->headers->has('ETag'));
    }

    #[DataProvider('unchangedResponses')]
    public function test_leaves_non_page_responses_unchanged(string $body, array $headers, int $status = 200): void
    {
        $response = new Response($body, $status, $headers);
        $originalHeaders = $response->headers->all();

        $result = (new ApplyBrowserRestrictions)->handle(Request::create('/test'), fn () => $response);

        $this->assertSame($body, $result->getContent());
        $this->assertSame($originalHeaders, $result->headers->all());
    }

    public static function unchangedResponses(): array
    {
        $html = '<html><head></head><body>Documento</body></html>';

        return [
            'json with embedded html' => [json_encode(['html' => $html]), ['Content-Type' => 'application/json']],
            'livewire fragment' => ['<div>Actualizado</div>', ['Content-Type' => 'text/html']],
            'pdf' => ['%PDF-1.7', ['Content-Type' => 'application/pdf']],
            'html download' => [$html, ['Content-Type' => 'text/html', 'Content-Disposition' => 'attachment; filename="reporte.html"']],
            'encoded response' => [$html, ['Content-Type' => 'text/html', 'Content-Encoding' => 'gzip']],
            'redirect' => [$html, ['Content-Type' => 'text/html', 'Location' => '/login'], 302],
        ];
    }

    public function test_leaves_streamed_responses_unchanged(): void
    {
        $response = new StreamedResponse(fn () => print ('stream'), 200, ['Content-Type' => 'text/html']);
        $result = (new ApplyBrowserRestrictions)->handle(Request::create('/stream'), fn () => $response);

        $this->assertSame($response, $result);
    }
}
