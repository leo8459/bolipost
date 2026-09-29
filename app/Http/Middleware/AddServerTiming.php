<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddServerTiming
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);
        $response = $next($request);
        $durationMs = (hrtime(true) - $startedAt) / 1_000_000;

        $response->headers->set('Server-Timing', sprintf('app;dur=%.2f', $durationMs));

        return $response;
    }
}
