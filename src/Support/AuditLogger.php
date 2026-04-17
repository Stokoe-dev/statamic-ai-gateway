<?php

namespace Stokoe\AiGateway\Support;

use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Sensitive keys that must never appear in audit log output.
     */
    private const EXCLUDED_KEYS = [
        'bearer_token',
        'authorization',
        'token',
        'confirmation_token',
        'raw_payload',
        'payload',
        'password',
        'secret',
    ];

    /**
     * Required fields that must appear in every audit log event.
     */
    private const REQUIRED_FIELDS = [
        'request_id',
        'tool',
        'status',
        'http_status',
        'target_type',
        'target_identifier',
        'environment',
        'duration_ms',
    ];

    public function log(array $context): void
    {
        $entry = $this->buildEntry($context);

        $channel = config('ai_gateway.audit.channel');

        $logger = $channel ? Log::channel($channel) : Log::getFacadeRoot();

        $logger->info('ai_gateway.audit', $entry);
    }

    /**
     * Build a sanitized audit log entry from the given context.
     */
    private function buildEntry(array $context): array
    {
        $entry = [];

        // Always include required fields (default to null if missing)
        foreach (self::REQUIRED_FIELDS as $field) {
            $entry[$field] = $context[$field] ?? null;
        }

        // Include error_code when present
        if (isset($context['error_code']) && $context['error_code'] !== null) {
            $entry['error_code'] = $context['error_code'];
        }

        // Include idempotency_key when provided
        if (isset($context['idempotency_key']) && $context['idempotency_key'] !== null) {
            $entry['idempotency_key'] = $context['idempotency_key'];
        }

        // Strip any sensitive keys that may have leaked in
        return $this->stripSensitive($entry);
    }

    /**
     * Remove any keys from the entry that match excluded patterns.
     */
    private function stripSensitive(array $entry): array
    {
        $filtered = [];

        foreach ($entry as $key => $value) {
            if (in_array(strtolower($key), self::EXCLUDED_KEYS, true)) {
                continue;
            }

            $filtered[$key] = $value;
        }

        return $filtered;
    }
}
