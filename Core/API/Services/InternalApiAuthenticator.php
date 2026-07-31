<?php

namespace Core\API\Services;

use Core\API\Support\InternalApiSigner;
use Core\Modules\Models\InstalledModule;
use Core\Modules\Services\InstalledModuleRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Authenticate module callers for /api/internal/* (token + HMAC + IP + nonce).
 */
class InternalApiAuthenticator
{
    public function __construct(
        private readonly InstalledModuleRepository $modules,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('corepanel.api.internal.enabled', true);
    }

    /**
     * @return array{module: InstalledModule, key: string}
     */
    public function authenticate(Request $request): array
    {
        if (! $this->enabled()) {
            abort(503, __('Internal API is disabled.'));
        }

        $this->assertIpAllowed($request);

        $moduleKey = trim((string) $request->headers->get(
            (string) config('corepanel.api.internal.module_header', 'X-CorePanel-Module'),
            '',
        ));

        if ($moduleKey === '') {
            abort(401, __('Module identity missing.'));
        }

        $module = $this->modules->find($moduleKey);

        if ($module === null) {
            abort(401, __('Unknown module.'));
        }

        if ((bool) config('corepanel.api.internal.require_enabled_module', true) && ! $module->enabled) {
            abort(403, __('Module is disabled.'));
        }

        $expectedToken = $this->resolveToken($module);

        if ($expectedToken === '') {
            abort(401, __('Module API token is not configured.'));
        }

        $providedToken = $this->extractToken($request);

        if ($providedToken === null || ! hash_equals($expectedToken, $providedToken)) {
            abort(401, __('Invalid module API token.'));
        }

        $secret = $this->resolveHmacSecret($module);

        if ($secret === '') {
            abort(401, __('Module HMAC secret is not configured.'));
        }

        $timestampHeader = (string) config('corepanel.api.internal.timestamp_header', 'X-CorePanel-Timestamp');
        $nonceHeader = (string) config('corepanel.api.internal.nonce_header', 'X-CorePanel-Nonce');
        $signatureHeader = (string) config('corepanel.api.internal.signature_header', 'X-CorePanel-Signature');

        $timestamp = trim((string) $request->headers->get($timestampHeader, ''));
        $nonce = trim((string) $request->headers->get($nonceHeader, ''));
        $signature = trim((string) $request->headers->get($signatureHeader, ''));

        if ($timestamp === '' || $nonce === '' || $signature === '') {
            abort(401, __('Missing HMAC authentication headers.'));
        }

        if (! ctype_digit($timestamp)) {
            abort(401, __('Invalid timestamp.'));
        }

        $skew = max(30, (int) config('corepanel.api.internal.max_skew_seconds', 300));
        $now = now()->getTimestamp();

        if (abs($now - (int) $timestamp) > $skew) {
            abort(401, __('Request timestamp is outside the allowed window.'));
        }

        if (strlen($nonce) < 8 || strlen($nonce) > 128) {
            abort(401, __('Invalid nonce.'));
        }

        $rawBody = (string) $request->getContent();

        if (! InternalApiSigner::verify($secret, $timestamp, $nonce, $rawBody, $signature)) {
            abort(401, __('Invalid request signature.'));
        }

        $this->assertNonceFresh($moduleKey, $nonce, $skew);

        $request->attributes->set('internal_module', $module);
        $request->attributes->set('internal_module_key', $moduleKey);

        return [
            'module' => $module,
            'key' => $moduleKey,
        ];
    }

    private function extractToken(Request $request): ?string
    {
        $headerName = (string) config('corepanel.api.internal.token_header', 'X-CorePanel-Module-Token');
        $headerToken = trim((string) $request->headers->get($headerName, ''));

        if ($headerToken !== '') {
            return $headerToken;
        }

        $authorization = trim((string) $request->header('Authorization', ''));

        if ($authorization !== '' && preg_match('/^Bearer\s+(\S+)$/i', $authorization, $matches) === 1) {
            $token = trim($matches[1]);

            return $token !== '' ? $token : null;
        }

        return null;
    }

    private function resolveToken(InstalledModule $module): string
    {
        $config = is_array($module->config) ? $module->config : [];
        $internal = is_array($config['internal_api'] ?? null) ? $config['internal_api'] : [];
        $moduleToken = trim((string) ($internal['token'] ?? ''));

        if ($moduleToken !== '') {
            return $moduleToken;
        }

        return trim((string) config('corepanel.api.internal.token', ''));
    }

    private function resolveHmacSecret(InstalledModule $module): string
    {
        $config = is_array($module->config) ? $module->config : [];
        $internal = is_array($config['internal_api'] ?? null) ? $config['internal_api'] : [];
        $moduleSecret = trim((string) ($internal['hmac_secret'] ?? ''));

        if ($moduleSecret !== '') {
            return $moduleSecret;
        }

        return trim((string) config('corepanel.api.internal.hmac_secret', ''));
    }

    private function assertIpAllowed(Request $request): void
    {
        if (! (bool) config('corepanel.api.internal.ip_whitelist.enabled', false)) {
            return;
        }

        $allowed = config('corepanel.api.internal.ip_whitelist.allowed', []);
        $allowed = is_array($allowed) ? array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $allowed,
        ), static fn (string $value): bool => $value !== '')) : [];

        if ($allowed === []) {
            abort(403, __('Internal API IP whitelist is empty.'));
        }

        $ip = (string) $request->ip();

        if ($ip === '' || ! IpUtils::checkIp($ip, $allowed)) {
            abort(403, __('IP address is not allowed for the internal API.'));
        }
    }

    private function assertNonceFresh(string $moduleKey, string $nonce, int $skew): void
    {
        $key = 'corepanel.internal_api.nonce.'.$moduleKey.'.'.hash('sha256', $nonce);
        $ttl = max(60, $skew * 2);

        if (! Cache::add($key, true, $ttl)) {
            abort(401, __('Replay detected: nonce already used.'));
        }
    }
}
