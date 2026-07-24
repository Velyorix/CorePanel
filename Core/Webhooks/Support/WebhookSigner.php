<?php

namespace Core\Webhooks\Support;

/**
 * HMAC-SHA256 signing for outgoing webhook payloads.
 */
final class WebhookSigner
{
    public static function sign(string $secret, string $timestamp, string $rawBody): string
    {
        $digest = hash_hmac('sha256', $timestamp.'.'.$rawBody, $secret);

        return 'sha256='.$digest;
    }

    public static function verify(
        string $secret,
        string $timestamp,
        string $rawBody,
        string $signatureHeader,
    ): bool
    {
        $expected = self::sign($secret, $timestamp, $rawBody);

        return hash_equals($expected, trim($signatureHeader));
    }
}
