<?php

namespace Stokoe\AiGateway\Support;

use Carbon\Carbon;

class ConfirmationTokenManager
{
    /**
     * Generate a signed confirmation token for the given tool and arguments.
     *
     * @return array{token: string, expires_at: string}
     */
    public function generate(string $tool, array $arguments): array
    {
        $timestamp = time();
        $ttl = (int) config('ai_gateway.confirmation.ttl', 60);
        $expiresAt = $timestamp + $ttl;

        $signature = $this->sign($tool, $arguments, $timestamp);

        // Encode timestamp into the token so we can check expiry without storage
        $token = base64_encode($timestamp . '.' . $signature);

        return [
            'token'      => $token,
            'expires_at' => Carbon::createFromTimestamp($expiresAt)->toIso8601String(),
        ];
    }

    /**
     * Validate a token against the given tool and arguments.
     */
    public function validate(string $token, string $tool, array $arguments): bool
    {
        $decoded = base64_decode($token, true);

        if ($decoded === false) {
            return false;
        }

        $parts = explode('.', $decoded, 2);

        if (count($parts) !== 2) {
            return false;
        }

        [$timestampStr, $signature] = $parts;

        if (! is_numeric($timestampStr)) {
            return false;
        }

        $timestamp = (int) $timestampStr;

        // Check expiry
        $ttl = (int) config('ai_gateway.confirmation.ttl', 60);
        if (time() > $timestamp + $ttl) {
            return false;
        }

        // Verify signature
        $expectedSignature = $this->sign($tool, $arguments, $timestamp);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Compute the HMAC-SHA256 signature for a tool + arguments + timestamp.
     */
    private function sign(string $tool, array $arguments, int $timestamp): string
    {
        $canonical = $tool . '|' . $this->canonicalize($arguments) . '|' . $timestamp;
        $key = config('app.key', '');

        return hash_hmac('sha256', $canonical, $key);
    }

    /**
     * Produce a canonical string representation of arguments for signing.
     */
    private function canonicalize(array $arguments): string
    {
        ksort($arguments);

        return json_encode($arguments, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
