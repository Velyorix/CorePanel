<?php

namespace Core\Modules\Services;

use Core\Modules\Exceptions\ModuleSandboxViolationException;

class ModuleTableAccessPolicy
{
    public function assertQueryAllowed(string $moduleKey, string $sql): void
    {
        foreach ($this->extractTables($sql) as $table) {
            $this->assertTableAllowed($moduleKey, $table, $sql);
        }
    }

    public function assertTableAllowed(string $moduleKey, string $table, ?string $sql = null): void
    {
        $table = strtolower(trim($table, '`"[]'));

        if ($table === '' || $this->isIgnoredSystemTable($table)) {
            return;
        }

        if ($this->isOwnModuleTable($moduleKey, $table)) {
            return;
        }

        if ($this->isAnyModuleTable($table)) {
            throw ModuleSandboxViolationException::foreignModuleTable($moduleKey, $table);
        }

        throw ModuleSandboxViolationException::coreTableAccess(
            $moduleKey,
            $table,
            $sql ?? $table,
        );
    }

    public function isOwnModuleTable(string $moduleKey, string $table): bool
    {
        $prefix = $this->modulePrefix($moduleKey);

        return str_starts_with(strtolower($table), strtolower($prefix));
    }

    public function modulePrefix(string $moduleKey): string
    {
        $base = (string) config('corepanel.modules.sandbox.module_table_prefix', 'module_');

        return $base.$moduleKey.'_';
    }

    /**
     * @return list<string>
     */
    public function extractTables(string $sql): array
    {
        $tables = [];

        if (preg_match_all(
            '/\b(?:from|join|into|update|table)\s+(?:[`"\[]?[a-zA-Z_][a-zA-Z0-9_]*[`"\]]?\.)?[`"\[]?([a-zA-Z_][a-zA-Z0-9_]*)[`"\]]?/i',
            $sql,
            $matches,
        ) === false) {
            return [];
        }

        foreach ($matches[1] as $table) {
            $normalized = strtolower($table);

            if (in_array($normalized, ['select', 'where', 'set', 'values', 'as'], true)) {
                continue;
            }

            $tables[] = $normalized;
        }

        return array_values(array_unique($tables));
    }

    private function isAnyModuleTable(string $table): bool
    {
        $base = strtolower((string) config('corepanel.modules.sandbox.module_table_prefix', 'module_'));

        return str_starts_with($table, $base);
    }

    private function isIgnoredSystemTable(string $table): bool
    {
        $ignored = config('corepanel.modules.sandbox.ignored_tables', [
            'sqlite_master',
            'sqlite_sequence',
            'sqlite_temp_master',
        ]);

        if (! is_array($ignored)) {
            return false;
        }

        return in_array($table, array_map('strtolower', $ignored), true);
    }
}
