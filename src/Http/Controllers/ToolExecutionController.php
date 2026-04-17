<?php

namespace Stokoe\AiGateway\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Stokoe\AiGateway\Exceptions\ToolAuthorizationException;
use Stokoe\AiGateway\Exceptions\ToolDisabledException;
use Stokoe\AiGateway\Exceptions\ToolNotFoundException;
use Stokoe\AiGateway\Exceptions\ToolValidationException;
use Stokoe\AiGateway\Policies\ToolPolicy;
use Stokoe\AiGateway\Support\AuditLogger;
use Stokoe\AiGateway\Support\ConfirmationTokenManager;
use Stokoe\AiGateway\Support\FieldFilter;
use Stokoe\AiGateway\Support\ToolRegistry;
use Stokoe\AiGateway\Support\ToolResponse;

class ToolExecutionController
{
    public function __construct(
        private ToolRegistry $registry,
        private ToolPolicy $policy,
        private FieldFilter $fieldFilter,
        private ConfirmationTokenManager $confirmationManager,
        private AuditLogger $auditLogger,
    ) {}

    public function execute(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $toolName = '';
        $requestId = null;
        $idempotencyKey = null;

        try {
            // 1. Validate content type
            if (! $this->isJsonContentType($request)) {
                return $this->buildErrorResponse('', 'validation_failed', 'Content-Type must be application/json.', 422);
            }

            // 2. Validate request body size
            $maxSize = (int) config('ai_gateway.max_request_size', 65536);
            if ((int) $request->header('Content-Length', 0) > $maxSize || strlen($request->getContent()) > $maxSize) {
                return $this->buildErrorResponse('', 'validation_failed', 'Request body exceeds maximum allowed size.', 422);
            }

            // 3. Validate envelope schema
            $payload = $request->json()->all();

            $envelopeValidator = Validator::make($payload, [
                'tool'               => ['required', 'string'],
                'arguments'          => ['required', 'array'],
                'request_id'         => ['sometimes', 'string'],
                'idempotency_key'    => ['sometimes', 'string'],
                'confirmation_token' => ['sometimes', 'string'],
            ]);

            if ($envelopeValidator->fails()) {
                return $this->buildErrorResponse(
                    $payload['tool'] ?? '',
                    'validation_failed',
                    'Invalid request envelope.',
                    422,
                    $this->buildMeta($payload['request_id'] ?? null),
                    $envelopeValidator->errors()->toArray(),
                );
            }

            $toolName = $payload['tool'];
            $arguments = $payload['arguments'];
            $requestId = $payload['request_id'] ?? null;
            $idempotencyKey = $payload['idempotency_key'] ?? null;
            $confirmationToken = $payload['confirmation_token'] ?? null;
            $meta = $this->buildMeta($requestId);

            // 4. Resolve tool via ToolRegistry
            $tool = $this->registry->resolve($toolName);

            // 5. Check tool-level authorization
            if (! $this->policy->toolEnabled($toolName)) {
                throw new ToolDisabledException($toolName);
            }

            // 6. Check target-level authorization
            $target = $tool->resolveTarget($arguments);
            if ($target !== null && ! $this->policy->targetAllowed($tool->targetType(), $target)) {
                throw new ToolAuthorizationException("Target '{$target}' is not allowed for tool '{$toolName}'.");
            }

            // 7. Filter denied fields
            $deniedFields = $this->policy->deniedFields($tool->targetType(), $target);
            if (isset($arguments['data']) && is_array($arguments['data'])) {
                $arguments['data'] = $this->fieldFilter->filter($arguments['data'], $deniedFields);
            }

            // 8. Check confirmation flow
            if ($tool->requiresConfirmation(app()->environment())) {
                if ($confirmationToken === null) {
                    $tokenData = $this->confirmationManager->generate($toolName, $arguments);
                    $response = ToolResponse::confirmationRequired(
                        $toolName,
                        $tokenData['token'],
                        $tokenData['expires_at'],
                        [
                            'tool'        => $toolName,
                            'target'      => $target,
                            'environment' => app()->environment(),
                        ],
                        $meta,
                    );
                    $this->logAudit($startTime, $requestId, $idempotencyKey, $toolName, 'rejected', 200, $tool->targetType(), $target, 'confirmation_required');
                    return $response->toJsonResponse();
                }

                if (! $this->confirmationManager->validate($confirmationToken, $toolName, $arguments)) {
                    // Token invalid or expired — issue a new one
                    $tokenData = $this->confirmationManager->generate($toolName, $arguments);
                    $response = ToolResponse::confirmationRequired(
                        $toolName,
                        $tokenData['token'],
                        $tokenData['expires_at'],
                        [
                            'tool'        => $toolName,
                            'target'      => $target,
                            'environment' => app()->environment(),
                        ],
                        $meta,
                    );
                    $this->logAudit($startTime, $requestId, $idempotencyKey, $toolName, 'rejected', 200, $tool->targetType(), $target, 'confirmation_required');
                    return $response->toJsonResponse();
                }
            }

            // 9. Run tool-specific validation
            $toolValidator = Validator::make($arguments, $tool->validationRules());
            if ($toolValidator->fails()) {
                throw new ToolValidationException(
                    'Tool argument validation failed.',
                    $toolValidator->errors()->toArray(),
                );
            }

            // 10. Execute tool
            $response = $tool->execute($arguments);

            // 10b. Filter denied fields from response data (for read tools)
            if (! empty($deniedFields)) {
                $response = $this->filterResponseData($response, $deniedFields);
            }

            // Attach meta to the response
            $jsonResponse = $this->attachMeta($response, $meta);

            // 11. Log via AuditLogger
            $status = $response->isOk() ? 'succeeded' : 'failed';
            $this->logAudit($startTime, $requestId, $idempotencyKey, $toolName, $status, $response->getHttpStatus(), $tool->targetType(), $target, null);

            return $jsonResponse;

        } catch (ToolNotFoundException $e) {
            $meta = $this->buildMeta($requestId);
            $this->logAudit($startTime, $requestId, $idempotencyKey, $toolName, 'rejected', $e->getHttpStatus(), null, null, $e->getErrorCode());
            return ToolResponse::error($toolName, $e->getErrorCode(), $e->getMessage(), $e->getHttpStatus(), $meta)->toJsonResponse();

        } catch (ToolDisabledException $e) {
            $meta = $this->buildMeta($requestId);
            $this->logAudit($startTime, $requestId, $idempotencyKey, $toolName, 'rejected', $e->getHttpStatus(), null, null, $e->getErrorCode());
            return ToolResponse::error($toolName, $e->getErrorCode(), $e->getMessage(), $e->getHttpStatus(), $meta)->toJsonResponse();

        } catch (ToolAuthorizationException $e) {
            $meta = $this->buildMeta($requestId);
            $this->logAudit($startTime, $requestId, $idempotencyKey, $toolName, 'rejected', $e->getHttpStatus(), null, null, $e->getErrorCode());
            return ToolResponse::error($toolName, $e->getErrorCode(), $e->getMessage(), $e->getHttpStatus(), $meta)->toJsonResponse();

        } catch (ToolValidationException $e) {
            $meta = $this->buildMeta($requestId);
            $this->logAudit($startTime, $requestId, $idempotencyKey, $toolName, 'rejected', $e->getHttpStatus(), null, null, $e->getErrorCode());
            return ToolResponse::error($toolName, $e->getErrorCode(), $e->getMessage(), $e->getHttpStatus(), $meta, $e->getDetails())->toJsonResponse();

        } catch (\Throwable $e) {
            $meta = $this->buildMeta($requestId);
            $isProduction = app()->environment('production');
            $errorCode = $isProduction ? 'internal_error' : 'execution_failed';
            $message = $isProduction ? 'An internal error occurred.' : $e->getMessage();

            $this->logAudit($startTime, $requestId, $idempotencyKey, $toolName, 'failed', 500, null, null, $errorCode);
            return ToolResponse::error($toolName, $errorCode, $message, 500, $meta)->toJsonResponse();
        }
    }

    public function toolUsage(Request $request, string $tool): JsonResponse
    {
        try {
            $handler = $this->registry->resolveWithoutEnabledCheck($tool);
        } catch (ToolNotFoundException $e) {
            return ToolResponse::error($tool, 'tool_not_found', $e->getMessage(), 404)->toJsonResponse();
        }

        $usage = $handler->describe();
        $usage['enabled'] = $this->registry->isEnabled($tool);
        $usage['requires_confirmation'] = $handler->requiresConfirmation(app()->environment());
        $usage['validation_rules'] = $handler->validationRules();

        // Add allowlist info
        $targetType = $handler->targetType();
        $allowlistMap = [
            'entry' => config('ai_gateway.allowed_collections', []),
            'global' => config('ai_gateway.allowed_globals', []),
            'navigation' => config('ai_gateway.allowed_navigations', []),
            'taxonomy' => config('ai_gateway.allowed_taxonomies', []),
            'cache' => config('ai_gateway.allowed_cache_targets', []),
        ];
        $usage['allowed_targets'] = $allowlistMap[$targetType] ?? [];

        // Add denied fields info
        $usage['denied_fields'] = $this->policy->deniedFields($targetType, null);

        return ToolResponse::success('tool_usage', $usage)->toJsonResponse();
    }

    public function capabilities(Request $request): JsonResponse
    {
        $allTools = $this->registry->all();
        $capabilities = [];

        foreach ($allTools as $name => $toolMeta) {
            $enabled = $toolMeta['enabled'];
            $handlerClass = $toolMeta['handler'];

            try {
                $instance = app()->make($handlerClass);
                $capabilities[$name] = [
                    'enabled'               => $enabled,
                    'target_type'           => $instance->targetType(),
                    'requires_confirmation' => $instance->requiresConfirmation(app()->environment()),
                ];
            } catch (\Throwable) {
                $capabilities[$name] = [
                    'enabled'               => $enabled,
                    'target_type'           => 'unknown',
                    'requires_confirmation' => false,
                ];
            }
        }

        return ToolResponse::success('capabilities', [
            'capabilities' => $capabilities,
        ])->toJsonResponse();
    }

    private function buildErrorResponse(
        string $tool,
        string $code,
        string $message,
        int $httpStatus,
        array $meta = [],
        ?array $details = null,
    ): JsonResponse {
        return ToolResponse::error($tool, $code, $message, $httpStatus, $meta, $details)->toJsonResponse();
    }

    private function isJsonContentType(Request $request): bool
    {
        $contentType = $request->header('Content-Type', '');

        return str_contains($contentType, 'application/json');
    }

    private function buildMeta(?string $requestId): array
    {
        $meta = [];

        if ($requestId !== null) {
            $meta['request_id'] = $requestId;
        }

        return $meta;
    }

    private function attachMeta(ToolResponse $response, array $meta): JsonResponse
    {
        $jsonResponse = $response->toJsonResponse();
        $data = json_decode($jsonResponse->getContent(), true);
        $data['meta'] = array_merge($data['meta'] ?? [], $meta);

        return new JsonResponse($data, $jsonResponse->getStatusCode());
    }

    private function logAudit(
        float $startTime,
        ?string $requestId,
        ?string $idempotencyKey,
        string $toolName,
        string $status,
        int $httpStatus,
        ?string $targetType,
        ?string $targetIdentifier,
        ?string $errorCode,
    ): void {
        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        $this->auditLogger->log([
            'request_id'        => $requestId,
            'idempotency_key'   => $idempotencyKey,
            'tool'              => $toolName,
            'status'            => $status,
            'http_status'       => $httpStatus,
            'target_type'       => $targetType,
            'target_identifier' => $targetIdentifier,
            'environment'       => app()->environment(),
            'duration_ms'       => $durationMs,
            'error_code'        => $errorCode,
        ]);
    }

    /**
     * Filter denied fields from response data for read tools.
     * Strips denied fields from 'data', 'entry.data', 'entries.*.data', 'term.data', 'terms.*.data'.
     */
    private function filterResponseData(ToolResponse $response, array $deniedFields): ToolResponse
    {
        $jsonResponse = $response->toJsonResponse();
        $payload = json_decode($jsonResponse->getContent(), true);

        if (! isset($payload['result']) || ! is_array($payload['result'])) {
            return $response;
        }

        $result = $payload['result'];

        // Single item: result.data, result.entry.data, result.term.data
        foreach (['data', 'entry.data', 'term.data'] as $path) {
            $data = data_get($result, $path);
            if (is_array($data)) {
                data_set($result, $path, $this->fieldFilter->filter($data, $deniedFields));
            }
        }

        // Collections: result.entries.*.data, result.terms.*.data
        foreach (['entries', 'terms'] as $listKey) {
            if (isset($result[$listKey]) && is_array($result[$listKey])) {
                foreach ($result[$listKey] as $i => $item) {
                    if (isset($item['data']) && is_array($item['data'])) {
                        $result[$listKey][$i]['data'] = $this->fieldFilter->filter($item['data'], $deniedFields);
                    }
                }
            }
        }

        // Rebuild the response with filtered result
        return ToolResponse::success($payload['tool'], $result, $payload['meta'] ?? []);
    }
}
