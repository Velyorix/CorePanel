<?php

namespace Core\Marketplace\Exceptions;

use RuntimeException;
use Throwable;

class MarketplaceInstallException extends RuntimeException
{
    public static function archiveMissing(string $slug, string $version): self
    {
        return new self("Marketplace package [{$slug}@{$version}] has no downloadable archive.");
    }

    public static function downloadFailed(string $message, ?Throwable $previous = null): self
    {
        return new self($message, 0, $previous);
    }

    public static function checksumMismatch(string $expected, string $actual): self
    {
        return new self("Marketplace package checksum mismatch. Expected [{$expected}], got [{$actual}].");
    }

    public static function invalidArchive(string $path, string $reason): self
    {
        return new self("Marketplace archive [{$path}] is invalid: {$reason}");
    }

    public static function packageAlreadyInstalled(string $path): self
    {
        return new self("Marketplace package destination [{$path}] already exists.");
    }

    public static function unsupportedProductType(string $type): self
    {
        return new self("Marketplace product type [{$type}] cannot be installed.");
    }

    public static function missingManifest(string $type): self
    {
        return new self("Extracted marketplace package does not contain a valid {$type} manifest.");
    }
}
