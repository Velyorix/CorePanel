<?php

namespace Core\API\Support;

/**
 * HMAC signing helpers for the internal module API.
 */
final class InternalApiSigner
{
    public static function sign(string $secret, string $timestamp, string $nonce, string $rawBody): string
    {
        $digest = hash_hmac('sha256', $timestamp.'.'.$nonce.'.'.$rawBody, $secret);

        return 'sha256='.$digest;
    }

    public static function verify(
        string $secret,
        string $timestamp,
        string $nonce,
        string $rawBody,
        string $signatureHeader,
    ): bool {
        $expected = self::sign($secret, $timestamp, $nonce, $rawBody);

        return hash_equals($expected, trim($signatureHeader));
    }
}
