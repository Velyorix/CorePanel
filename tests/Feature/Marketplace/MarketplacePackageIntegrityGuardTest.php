<?php

namespace Tests\Feature\Marketplace;

use Core\Marketplace\DataTransferObjects\MarketplaceDownloadDescriptor;
use Core\Marketplace\Exceptions\MarketplaceInstallException;
use Core\Marketplace\Services\MarketplacePackageIntegrityGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MarketplacePackageIntegrityGuardTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'guard-test-secret';

    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempPath = storage_path('framework/testing/marketplace-integrity-'.uniqid('', true));
        File::ensureDirectoryExists($this->tempPath);

        config([
            'corepanel.marketplace.integrity.enforce_checksum' => true,
            'corepanel.marketplace.integrity.verify_signature' => true,
            'corepanel.marketplace.integrity.enforce_signature' => true,
            'corepanel.marketplace.integrity.signature_secret' => self::SECRET,
        ]);

        $this->app->forgetInstance(MarketplacePackageIntegrityGuard::class);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->tempPath);

        parent::tearDown();
    }

    public function test_accepts_valid_descriptor_and_archive(): void
    {
        $contents = 'marketplace package payload';
        $path = $this->tempPath.'/valid.zip';
        File::put($path, $contents);

        $checksum = hash('sha256', $contents);
        $descriptor = $this->descriptor($checksum, $this->sign($checksum), strlen($contents));

        $guard = app(MarketplacePackageIntegrityGuard::class);
        $guard->assertDescriptor($descriptor);
        $guard->assertArchive($path, $descriptor);

        $this->addToAssertionCount(1);
    }

    public function test_rejects_missing_checksum_on_descriptor(): void
    {
        $descriptor = $this->descriptor(null, null, 100);

        $this->expectException(MarketplaceInstallException::class);
        $this->expectExceptionMessage('missing a required SHA-256 checksum');

        app(MarketplacePackageIntegrityGuard::class)->assertDescriptor($descriptor);
    }

    public function test_rejects_invalid_signature_on_descriptor(): void
    {
        $checksum = hash('sha256', 'payload');
        $descriptor = $this->descriptor($checksum, str_repeat('a', 64), 100);

        $this->expectException(MarketplaceInstallException::class);
        $this->expectExceptionMessage('signature is invalid');

        app(MarketplacePackageIntegrityGuard::class)->assertDescriptor($descriptor);
    }

    public function test_skips_signature_when_verification_disabled(): void
    {
        config([
            'corepanel.marketplace.integrity.verify_signature' => false,
            'corepanel.marketplace.integrity.enforce_signature' => false,
        ]);
        $this->app->forgetInstance(MarketplacePackageIntegrityGuard::class);

        $checksum = hash('sha256', 'payload');
        $descriptor = $this->descriptor($checksum, null, 100);

        app(MarketplacePackageIntegrityGuard::class)->assertDescriptor($descriptor);

        $this->addToAssertionCount(1);
    }

    public function test_rejects_archive_size_mismatch(): void
    {
        $contents = 'payload';
        $path = $this->tempPath.'/size.zip';
        File::put($path, $contents);

        $checksum = hash('sha256', $contents);
        $descriptor = $this->descriptor($checksum, $this->sign($checksum), strlen($contents) + 10);

        $this->expectException(MarketplaceInstallException::class);
        $this->expectExceptionMessage('size mismatch');

        app(MarketplacePackageIntegrityGuard::class)->assertArchive($path, $descriptor);
    }

    private function descriptor(?string $checksum, ?string $signature, ?int $sizeBytes): MarketplaceDownloadDescriptor
    {
        $payload = [
            'download_url' => 'https://cdn.corepanel.test/package.zip',
            'filename' => 'package.zip',
        ];

        if ($checksum !== null) {
            $payload['checksum_sha256'] = $checksum;
        }

        if ($signature !== null) {
            $payload['signature_hmac_sha256'] = $signature;
        }

        if ($sizeBytes !== null) {
            $payload['size_bytes'] = $sizeBytes;
        }

        return MarketplaceDownloadDescriptor::fromArray($payload);
    }

    private function sign(string $checksum): string
    {
        return hash_hmac('sha256', strtolower($checksum), self::SECRET);
    }
}
