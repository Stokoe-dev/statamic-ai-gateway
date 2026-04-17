<?php

namespace Stokoe\AiGateway\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Stokoe\AiGateway\Support\ToolResponse;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateGateway
{
    /**
     * Handle an incoming request.
     *
     * Validates the Authorization: Bearer <token> header using timing-safe comparison.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedToken = config('ai_gateway.token');

        $header = $request->header('Authorization');

        if ($header === null) {
            return $this->unauthorized();
        }

        if (! str_starts_with($header, 'Bearer ')) {
            return $this->unauthorized();
        }

        $providedToken = substr($header, 7);

        if ($providedToken === '' || $expectedToken === null || $expectedToken === '') {
            return $this->unauthorized();
        }

        if (! hash_equals((string) $expectedToken, $providedToken)) {
            return $this->unauthorized();
        }

        return $next($request);
    }

    /**
     * Return a 401 unauthorized response using the ToolResponse envelope.
     */
    private function unauthorized(): Response
    {
        return ToolResponse::error(
            tool: '',
            code: 'unauthorized',
            message: 'Missing or invalid bearer token.',
            httpStatus: 401,
        )->toJsonResponse();
    }
}
