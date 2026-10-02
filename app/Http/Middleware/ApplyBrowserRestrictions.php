<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApplyBrowserRestrictions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only complete browser documents; leave API/Livewire fragments and downloads intact.
        if (
            $response instanceof BinaryFileResponse
            || $response instanceof StreamedResponse
            || $response->isRedirection()
            || $response->headers->has('Content-Disposition')
            || $response->headers->has('Content-Encoding')
            || ! str_starts_with(strtolower($response->headers->get('Content-Type', '')), 'text/html')
        ) {
            return $response;
        }

        $html = $response->getContent();

        if (
            ! is_string($html)
            || ! preg_match('/\A\s*(?:<!doctype\s+html\s*>\s*)?<html\b/i', $html)
            || ! preg_match('/<head\b[^>]*>/i', $html)
        ) {
            return $response;
        }

        $assets = view('partials.browser-restrictions')->render();
        $html = preg_replace_callback('/<head\b[^>]*>/i', fn ($match) => $match[0].$assets, $html, 1);

        $response->setContent($html);
        $response->headers->remove('Content-Length');
        $response->headers->remove('ETag');

        return $response;
    }
}
