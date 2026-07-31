<?php

namespace Core\Modules\Services;

use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\ModuleSignatureException;

class ModuleSignatureVerifier
{
    public function __construct(
        private readonly ModulePackageHasher $hasher,
    ) {
    }

    /**
     * @return array{checksum: string, signature: string|null, verified: bool}
     */
    public function verify(ModuleManifest $manifest, bool $requireDeclared = false): array
    {
        $actual = $this->hasher->hash($manifest);
        $expectedChecksum = $this->expectedChecksum($manifest);
        $expectedSignature = $this->expectedSignature($manifest);
        $required = $requireDeclared || $this->isRequired();

        if ($expectedChecksum === null && $expectedSignature === null) {
            if ($required) {
                throw ModuleSignatureException::missing($manifest->key);
            }

            return [
                'checksum' => $actual,
                'signature' => $this->sign($actual),
                'verified' => false,
            ];
        }

        if ($expectedChecksum !== null && ! hash_equals($expectedChecksum, strtolower($actual))) {
            throw ModuleSignatureException::mismatch($manifest->key);
        }

        if ($expectedSignature !== null) {
            $accepted = [
                $this->sign($actual),
                strtolower($actual),
            ];

            if ($expectedChecksum !== null) {
                $accepted[] = $this->sign($expectedChecksum);
            }

            $matched = false;

            foreach ($accepted as $candidate) {
                if (hash_equals(strtolower($candidate), $expectedSignature)) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                throw ModuleSignatureException::mismatch($manifest->key);
            }
        }

        return [
            'checksum' => $actual,
            'signature' => $expectedSignature ?? $this->sign($actual),
            'verified' => true,
        ];
    }

    public function assertValid(ModuleManifest $manifest): void
    {
        $this->verify($manifest, requireDeclared: $this->isRequired());
    }

    public function sign(string $checksum): string
    {
        $secret = (string) (
            config('corepanel.modules.signature.secret')
            ?: config('app.key')
            ?: 'corepanel-modules'
        );

        return hash_hmac('sha256', strtolower($checksum), $secret);
    }

    public function isRequired(): bool
    {
        return (bool) config('corepanel.modules.signature.required', false);
    }

    private function expectedChecksum(ModuleManifest $manifest): ?string
    {
        $fromManifest = $manifest->raw['checksum'] ?? null;

        if (is_string($fromManifest) && trim($fromManifest) !== '') {
            return strtolower(trim($fromManifest));
        }

        $file = $manifest->path.DIRECTORY_SEPARATOR.'module.sha256';

        if (! is_file($file)) {
            return null;
        }

        $contents = file_get_contents($file);

        if ($contents === false) {
            return null;
        }

        $line = trim(explode("\n", $contents)[0] ?? '');
        $hash = preg_split('/\s+/', $line)[0] ?? '';

        return is_string($hash) && $hash !== '' ? strtolower($hash) : null;
    }

    private function expectedSignature(ModuleManifest $manifest): ?string
    {
        $signature = $manifest->raw['signature'] ?? null;

        if (! is_string($signature) || trim($signature) === '') {
            return null;
        }

        return strtolower(trim($signature));
    }
}
