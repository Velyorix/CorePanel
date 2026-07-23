<?php

namespace Core\Marketplace\Exceptions;

use RuntimeException;
use Throwable;

class MarketplaceApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        string $message,
        public readonly ?int $statusCode = null,
        public readonly ?string $errorCode = null,
        public readonly array $details = [],
        public readonly array $raw = [],
        public readonly ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function connectionFailed(string $message, ?Throwable $previous = null): self
    {
        return new self(
            message: $message,
            errorCode: 'request_failed',
            previous: $previous,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromResponse(array $payload, int $statusCode, ?int $retryAfterHeader = null): self
    {
        $error = is_array($payload['error'] ?? null) ? $payload['error'] : [];
        $details = is_array($error['details'] ?? null) ? $error['details'] : [];
        $retryAfter = $retryAfterHeader;

        if ($retryAfter === null && isset($details['retry_after']) && is_numeric($details['retry_after'])) {
            $retryAfter = (int) $details['retry_after'];
        }

        $code = isset($error['code']) ? (string) $error['code'] : 'request_failed';
        $message = isset($error['message']) && is_string($error['message']) && $error['message'] !== ''
            ? $error['message']
            : 'Marketplace API request failed.';

        return new self(
            message: $message,
            statusCode: $statusCode,
            errorCode: $code,
            details: $details,
            raw: $payload,
            retryAfter: $retryAfter,
        );
    }
}
