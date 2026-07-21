<?php

namespace Core\Modules\Services;

use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\ModuleBootstrapException;

class ModuleRequirementChecker
{
    public function assertSatisfied(ModuleManifest $manifest): void
    {
        $requires = $manifest->requires;

        if ($requires->isEmpty()) {
            return;
        }

        if ($requires->php !== null && ! $this->phpSatisfies($requires->php)) {
            throw ModuleBootstrapException::requirementFailed(
                $manifest->key,
                "PHP {$requires->php} required, running ".PHP_VERSION.'.',
            );
        }

        if ($requires->corepanel !== null && ! $this->corepanelSatisfies($requires->corepanel)) {
            $installed = (string) config('corepanel.version', '0.0.0');

            throw ModuleBootstrapException::requirementFailed(
                $manifest->key,
                "CorePanel {$requires->corepanel} required, installed {$installed}.",
            );
        }
    }

    public function phpSatisfies(string $constraint): bool
    {
        return $this->satisfies(PHP_VERSION, $constraint);
    }

    public function corepanelSatisfies(string $constraint): bool
    {
        $version = $this->normalizeCorepanelVersion((string) config('corepanel.version', '0.0.0'));

        return $this->satisfies($version, $constraint);
    }

    private function satisfies(string $version, string $constraint): bool
    {
        $version = $this->normalizeVersion($version);
        $constraint = trim($constraint);

        if ($constraint === '*' || $constraint === '') {
            return true;
        }

        if (preg_match('/^[<>=!~^]/', $constraint) !== 1) {
            $constraint = '>='.$constraint;
        }

        if (preg_match('/^(>=|<=|>|<|==|=)\s*(.+)$/', $constraint, $matches) !== 1) {
            return version_compare($version, $this->normalizeVersion($constraint), '>=');
        }

        $operator = $matches[1] === '=' ? '==' : $matches[1];

        return version_compare($version, $this->normalizeVersion($matches[2]), $operator);
    }

    private function normalizeCorepanelVersion(string $version): string
    {
        $version = trim($version);

        if ($version === '' || str_contains($version, 'dev')) {
            return '0.0.0';
        }

        return $this->normalizeVersion($version);
    }

    private function normalizeVersion(string $version): string
    {
        $version = ltrim(trim($version), 'vV');

        if (preg_match('/^\d+\.\d+\.\d+/', $version, $matches) === 1) {
            return $matches[0];
        }

        return $version;
    }
}
