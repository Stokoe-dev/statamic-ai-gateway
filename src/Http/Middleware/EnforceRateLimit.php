<?php

namespace Stokoe\AiGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Stokoe\AiGateway\Support\ToolResponse;
use Symfony\Component\HttpFoundation\Response;

class EnforceRateLimit
{
    /**
     * Handle an incoming request.
     *
     * Applies per-token rate limiting using separate buckets for execute and capabilities endpoints.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $this->extractToken($request);
        $tokenHash = hash('sha256', $token);

        $isExecute = $request->isMethod('POST') && str_ends_with($request->path(), 'execute');

        if ($isExecute) {
            $maxAttempts = (int) config('ai_gateway.rate_limits.execute', 30);
            $key = 'ai_gateway:execute:' . $tokenHash;
        } else {
            $maxAttempts = (int) config('ai_gateway.rate_limits.capabilities', 60);
            $key = 'ai_gateway:capabilities:' . $tokenHash;
        }

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return $this->rateLimited();
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }

    /**
     * Extract the bearer token from the Authorization header.
     */
    private function extractToken(Request $request): string
    {
        $header = $request->header('Authorization', '');

        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return '';
    }

    /**
     * Return a 429 rate limited response using the ToolResponse envelope.
     */
    private function rateLimited(): Response
    {
        return ToolResponse::error(
            tool: '',
            code: 'rate_limited',
            message: 'Rate limit exceeded. Please try again later.',
            httpStatus: 429,
        )->toJsonResponse();
    }
}
