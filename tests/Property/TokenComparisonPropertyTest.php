<?php

namespace Stokoe\AiGateway\Tests\Property;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\Test;
use Stokoe\AiGateway\Http\Middleware\AuthenticateGateway;
use Stokoe\AiGateway\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * Feature: ai-gateway, Property 1: Token comparison correctness
 *
 * For any two strings A and B, the authentication middleware's token comparison
 * returns true if and only if A and B are identical, and returns false for all
 * non-matching strings regardless of length, prefix, or character composition.
 *
 * **Validates: Requirements 2.3, 2.4**
 */
class TokenComparisonPropertyTest extends TestCase
{
    use TestTrait;

    /**
     * Property 1: Identical tokens always authenticate successfully.
     */
    #[Test]
    public function identical_tokens_always_authenticate(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 100,
                Generators::string()
            ),
        )
            ->withMaxSize(100)
            ->__invoke(function (string $token): void {
                config(['ai_gateway.token' => $token]);

                $middleware = new AuthenticateGateway();

                $request = Request::create('/ai-gateway/execute', 'POST');
                $request->headers->set('Authorization', 'Bearer ' . $token);

                $passed = false;
                $response = $middleware->handle($request, function () use (&$passed) {
                    $passed = true;

                    return new JsonResponse(['ok' => true], 200);
                });

                $this->assertTrue($passed, 'Identical token must pass authentication');
            });
    }

    /**
     * Property 1: Non-matching tokens always fail authentication with 401.
     */
    #[Test]
    public function non_matching_tokens_always_fail(): void
    {
        $this->forAll(
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 100,
                Generators::string()
            ),
            Generators::suchThat(
                fn ($s) => is_string($s) && strlen($s) > 0 && strlen($s) <= 100,
                Generators::string()
            ),
        )
            ->withMaxSize(100)
            ->__invoke(function (string $configuredToken, string $providedToken): void {
                if ($configuredToken === $providedToken) {
                    // Skip identical pairs — covered by the other test
                    return;
                }

                config(['ai_gateway.token' => $configuredToken]);

                $middleware = new AuthenticateGateway();

                $request = Request::create('/ai-gateway/execute', 'POST');
                $request->headers->set('Authorization', 'Bearer ' . $providedToken);

                $passed = false;
                $response = $middleware->handle($request, function () use (&$passed) {
                    $passed = true;

                    return new JsonResponse(['ok' => true], 200);
                });

                $this->assertFalse($passed, 'Non-matching token must not pass authentication');
                $this->assertEquals(401, $response->getStatusCode());

                $body = json_decode($response->getContent(), true);
                $this->assertFalse($body['ok']);
                $this->assertEquals('unauthorized', $body['error']['code']);
            });
    }
}
