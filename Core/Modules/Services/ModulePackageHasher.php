<?php

namespace Core\Modules\Services;

use Core\Modules\DataTransferObjects\ModuleManifest;
use Core\Modules\Exceptions\ModuleSignatureException;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class ModulePackageHasher
{
    /**
     * @var list<string>
     */
    private array $excludedDirectoryNames = [
        '.git',
        'node_modules',
        'vendor',
        '.idea',
        '.vscode',
    ];

    /**
     * Build a stable SHA-256 digest of the module package contents.
     */
    public function hash(ModuleManifest|string $module): string
    {
        $root = $module instanceof ModuleManifest ? $module->path : $module;
        $root = rtrim($root, DIRECTORY_SEPARATOR.'/\\');

        if (! is_dir($root)) {
            $key = $module instanceof ModuleManifest ? $module->key : basename($root);

            throw ModuleSignatureException::unreadable($key, $root);
        }

        $hash = hash_init('sha256');
        $files = $this->collectFiles($root);

        foreach ($files as $relative => $absolute) {
            hash_update($hash, $relative."\n");

            $contents = file_get_contents($absolute);

            if ($contents === false) {
                $key = $module instanceof ModuleManifest ? $module->key : basename($root);

                throw ModuleSignatureException::unreadable($key, $absolute);
            }

            if (basename($relative) === 'module.json') {
                $contents = $this->canonicalManifestContents($contents);
            }

            hash_update($hash, hash('sha256', $contents)."\n");
        }

        return hash_final($hash);
    }

    private function canonicalManifestContents(string $contents): string
    {
        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $contents;
        }

        unset($data['checksum'], $data['signature']);

        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? $contents : $encoded;
    }

    /**
     * @return array<string, string> relative => absolute
     */
    private function collectFiles(string $root): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        $files = [];

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolute = $file->getPathname();
            $relative = str_replace('\\', '/', substr($absolute, strlen($root) + 1));

            if ($this->shouldSkip($relative)) {
                continue;
            }

            $files[$relative] = $absolute;
        }

        ksort($files);

        return $files;
    }

    private function shouldSkip(string $relative): bool
    {
        $segments = explode('/', $relative);

        foreach ($segments as $segment) {
            if (in_array($segment, $this->excludedDirectoryNames, true)) {
                return true;
            }
        }

        $basename = basename($relative);

        return in_array($basename, ['module.sha256', '.DS_Store', 'Thumbs.db'], true);
    }
}
