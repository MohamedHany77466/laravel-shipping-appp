<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $key = 'global', int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $identifier = $this->resolveRequestSignature($request, $key);

        if (RateLimiter::tooManyAttempts($identifier, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($identifier);
            
            return response()->json([
                'message' => 'تم تجاوز الحد المسموح من الطلبات',
                'retry_after' => $seconds
            ], 429);
        }

        RateLimiter::hit($identifier, $decayMinutes * 60);

        $response = $next($request);

        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => RateLimiter::remaining($identifier, $maxAttempts),
        ]);
    }

    /**
     * Resolve the request signature for rate limiting.
     */
    protected function resolveRequestSignature(Request $request, string $key): string
    {
        if ($key === 'auth') {
            return 'auth:' . $request->ip();
        }

        if ($key === 'api') {
            $user = $request->user();
            return $user ? 'api:user:' . $user->id : 'api:ip:' . $request->ip();
        }

        return $key . ':' . $request->ip();
    }
}