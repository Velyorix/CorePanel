<?php

namespace Core\Marketplace\Services;

use Core\Marketplace\Exceptions\MarketplaceInstallException;
use Illuminate\Support\Facades\File;
use ZipArchive;

/**
 * Extracts marketplace zip archives into a staging directory.
 */
class MarketplacePackageExtractor
{
    /**
     * Extract a zip archive and return the package root (folder containing the manifest).
     */
    public function extract(string $archivePath, string $stagingDirectory, string $manifestFilename): string
    {
        if (! is_file($archivePath)) {
            throw MarketplaceInstallException::invalidArchive($archivePath, 'file does not exist.');
        }

        File::ensureDirectoryExists($stagingDirectory);

        $zip = new ZipArchive;

        if ($zip->open($archivePath) !== true) {
            throw MarketplaceInstallException::invalidArchive($archivePath, 'unable to open zip archive.');
        }

        try {
            $stagingRoot = realpath($stagingDirectory);

            if ($stagingRoot === false) {
                throw MarketplaceInstallException::invalidArchive($archivePath, 'staging directory is invalid.');
            }

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = $zip->getNameIndex($index);

                if (! is_string($entryName) || $entryName === '' || str_ends_with($entryName, '/')) {
                    continue;
                }

                $normalized = str_replace('\\', '/', $entryName);

                if (str_contains($normalized, '..') || str_starts_with($normalized, '/')) {
                    throw MarketplaceInstallException::invalidArchive($archivePath, 'zip slip path detected.');
                }

                $target = $stagingRoot.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalized);
                $targetDir = dirname($target);

                File::ensureDirectoryExists($targetDir);

                $stream = $zip->getStream($entryName);

                if ($stream === false) {
                    throw MarketplaceInstallException::invalidArchive($archivePath, "unable to read entry [{$entryName}].");
                }

                $contents = stream_get_contents($stream);
                fclose($stream);

                if ($contents === false || file_put_contents($target, $contents) === false) {
                    throw MarketplaceInstallException::invalidArchive($archivePath, "unable to write entry [{$entryName}].");
                }
            }
        } finally {
            $zip->close();
        }

        $packageRoot = $this->locatePackageRoot($stagingRoot, $manifestFilename);

        if ($packageRoot === null) {
            throw MarketplaceInstallException::missingManifest($manifestFilename === 'theme.json' ? 'theme' : 'module');
        }

        return $packageRoot;
    }

    private function locatePackageRoot(string $stagingRoot, string $manifestFilename): ?string
    {
        if (is_file($stagingRoot.DIRECTORY_SEPARATOR.$manifestFilename)) {
            return $stagingRoot;
        }

        foreach (scandir($stagingRoot) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $candidate = $stagingRoot.DIRECTORY_SEPARATOR.$entry;

            if (is_dir($candidate) && is_file($candidate.DIRECTORY_SEPARATOR.$manifestFilename)) {
                return $candidate;
            }
        }

        return null;
    }
}
