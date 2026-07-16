<?php

namespace Core\License\DataTransferObjects;

class LicenseValidationResult
{
    /**
     * @param  array<string, mixed>  $license
     * @param  array<string, mixed>  $activation
     * @param  list<array<string, mixed>>  $entitlements
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly bool $valid,
        public readonly ?int $statusCode,
        public readonly ?string $reason,
        public readonly ?string $message,
        public readonly array $license,
        public readonly array $activation,
        public readonly array $entitlements,
        public readonly array $raw,
        public readonly ?int $retryAfter = null,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload, ?int $statusCode, ?int $retryAfterHeader = null): self
    {
        // Flat license envelope (validate/activate success or business failure).
        if (array_key_exists('valid', $payload)) {
            return new self(
                valid: (bool) $payload['valid'],
                statusCode: $statusCode,
                reason: isset($payload['reason']) ? (string) $payload['reason'] : null,
                message: isset($payload['message']) ? (string) $payload['message'] : null,
                license: is_array($payload['license'] ?? null) ? $payload['license'] : [],
                activation: is_array($payload['activation'] ?? null) ? $payload['activation'] : [],
                entitlements: is_array($payload['entitlements'] ?? null) ? array_values($payload['entitlements']) : [],
                raw: $payload,
                retryAfter: $retryAfterHeader,
            );
        }

        // Standard API error envelope: { error: { code, message, details } }
        if (is_array($payload['error'] ?? null)) {
            $error = $payload['error'];
            $retryAfter = $retryAfterHeader;

            if (isset($error['details']['retry_after']) && is_numeric($error['details']['retry_after'])) {
                $retryAfter = (int) $error['details']['retry_after'];
            }

            return new self(
                valid: false,
                statusCode: $statusCode,
                reason: isset($error['code']) ? (string) $error['code'] : 'request_failed',
                message: isset($error['message']) ? (string) $error['message'] : null,
                license: [],
                activation: [],
                entitlements: [],
                raw: $payload,
                retryAfter: $retryAfter,
            );
        }

        return new self(
            valid: false,
            statusCode: $statusCode,
            reason: 'request_failed',
            message: __('Unexpected response from CorePanel.org.'),
            license: [],
            activation: [],
            entitlements: [],
            raw: $payload,
            retryAfter: $retryAfterHeader,
        );
    }

    public static function requestFailed(string $message): self
    {
        return new self(
            valid: false,
            statusCode: null,
            reason: 'request_failed',
            message: $message,
            license: [],
            activation: [],
            entitlements: [],
            raw: [],
            retryAfter: null,
        );
    }
}
