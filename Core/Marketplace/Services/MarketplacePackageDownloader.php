<?php

namespace Core\Marketplace\Services;

use Core\Marketplace\DataTransferObjects\MarketplaceDownloadDescriptor;
use Core\Marketplace\Exceptions\MarketplaceInstallException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Downloads marketplace package archives to a local temporary path.
 */
class MarketplacePackageDownloader
{
    public function download(MarketplaceDownloadDescriptor $descriptor, ?string $destinationDirectory = null): string
    {
        $directory = $destinationDirectory
            ?? (string) config('corepanel.marketplace.install.temp_path', storage_path('app/marketplace/tmp'));

        File::ensureDirectoryExists($directory);

        $filename = $this->safeFilename($descriptor->filename ?? 'package.zip');
        $destination = $directory.DIRECTORY_SEPARATOR.uniqid('pkg_', true).'_'.$filename;

        try {
            $request = Http::timeout((int) config('corepanel.marketplace.install.download_timeout_seconds', 120))
                ->withOptions(['sink' => $destination]);

            if (app()->environment('local', 'testing') || ! (bool) config('corepanel.org.verify_ssl', true)) {
                $request = $request->withoutVerifying();
            }

            $response = $request->get($descriptor->downloadUrl);
        } catch (ConnectionException $exception) {
            $this->deleteIfExists($destination);

            throw MarketplaceInstallException::downloadFailed($exception->getMessage(), $exception);
        } catch (Throwable $exception) {
            $this->deleteIfExists($destination);

            throw MarketplaceInstallException::downloadFailed($exception->getMessage(), $exception);
        }

        if (! $response->successful()) {
            $this->deleteIfExists($destination);

            throw MarketplaceInstallException::downloadFailed(
                'Archive download failed with HTTP '.$response->status().'.',
            );
        }

        if (! is_file($destination) || filesize($destination) === 0) {
            $this->deleteIfExists($destination);

            throw MarketplaceInstallException::downloadFailed('Downloaded marketplace archive is empty.');
        }

        if (is_string($descriptor->checksumSha256) && $descriptor->checksumSha256 !== '') {
            $actual = hash_file('sha256', $destination);

            if (! is_string($actual) || ! hash_equals($descriptor->checksumSha256, strtolower($actual))) {
                $this->deleteIfExists($destination);

                throw MarketplaceInstallException::checksumMismatch(
                    $descriptor->checksumSha256,
                    is_string($actual) ? strtolower($actual) : 'unreadable',
                );
            }
        }

        return $destination;
    }

    private function safeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'package.zip';

        return $filename !== '' ? $filename : 'package.zip';
    }

    private function deleteIfExists(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }
}
