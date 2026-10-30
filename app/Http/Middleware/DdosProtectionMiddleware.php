<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DdosProtectionMiddleware
{
    /**
     * Max payload size allowed (in bytes) - 5MB maximum to prevent body-flooding DDoS.
     */
    protected int $maxPayloadBytes = 5 * 1024 * 1024;

    /**
     * Micro-burst threshold: maximum requests allowed within a 2-second sliding window.
     * Prevents rapid bot spam and packet flooding.
     */
    protected int $maxBurstPerTwoSeconds = 25;

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip() ?? 'unknown';

        // 1. Guard against payload flooding (DDoS via oversized body)
        $contentLength = (int) $request->header('Content-Length', 0);
        if ($contentLength > $this->maxPayloadBytes) {
            Log::warning("DDoS Defense: Oversized payload rejected from IP: {$ip} (size: {$contentLength} bytes)");
            return response()->json([
                'success' => false,
                'error' => 'Payload Too Large',
                'message' => 'Request payload exceeds the maximum allowed security limit (5MB).',
            ], 413);
        }

        // 2. Micro-burst flood circuit breaker (detects automated flood tools)
        $burstKey = "ddos_burst:" . sha1($ip);
        $currentBurst = (int) Cache::get($burstKey, 0);

        if ($currentBurst >= $this->maxBurstPerTwoSeconds) {
            Log::warning("DDoS Defense: Burst rate limit exceeded by IP: {$ip} ({$currentBurst} reqs/2s)");
            return response()->json([
                'success' => false,
                'error' => 'DDoS Burst Protection Triggered',
                'message' => 'Security circuit breaker engaged: Too many rapid requests detected from your IP. Please slow down and wait a moment.',
                'retry_after' => 2,
            ], 429, [
                'Retry-After' => '2',
                'X-RateLimit-Limit' => (string) $this->maxBurstPerTwoSeconds,
                'X-RateLimit-Remaining' => '0',
            ]);
        }

        // Increment burst counter with a 2-second TTL
        if ($currentBurst === 0) {
            Cache::put($burstKey, 1, 2);
        } else {
            Cache::increment($burstKey);
        }

        // 3. Process the request
        $response = $next($request);

        // 4. Inject OWASP Security Headers into all responses
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
