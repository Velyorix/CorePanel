<?php

namespace Core\Marketplace\Services;

use Core\Marketplace\DataTransferObjects\MarketplaceCatalogPage;
use Core\Marketplace\DataTransferObjects\MarketplaceProduct;
use Core\Marketplace\DataTransferObjects\MarketplaceProductVersion;
use Core\Marketplace\DataTransferObjects\MarketplaceVersionList;
use Core\Marketplace\Exceptions\MarketplaceApiException;
use Core\Marketplace\Support\MarketplaceCatalogCredentials;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * HTTP client for the corepanel.org marketplace catalogue and versions API.
 *
 * Responses are cached locally (Redis by default) with a configurable TTL.
 */
class MarketplaceClient
{
    public function __construct(
        private readonly MarketplaceCatalogCache $cache,
    ) {
    }

    /**
     * List marketplace products (paginated catalogue).
     *
     * @param  array{
     *     q?: string|null,
     *     category?: string|null,
     *     price?: string|null,
     *     product_type?: string|null,
     *     sort?: string|null,
     *     page?: int|null,
     *     per_page?: int|null
     * }  $filters
     */
    public function listProducts(array $filters = []): MarketplaceCatalogPage
    {
        $query = $this->catalogQuery($filters);

        /** @var array<string, mixed> $payload */
        $payload = $this->cache->remember(
            $this->cache->productsKey($query),
            fn (): array => $this->fetchJson('marketplace/products', $query),
        );

        return MarketplaceCatalogPage::fromResponse($payload);
    }

    /**
     * Fetch a single marketplace product by slug.
     */
    public function getProduct(string $productSlug): MarketplaceProduct
    {
        $slug = $this->assertSlug($productSlug);

        /** @var array<string, mixed> $payload */
        $payload = $this->cache->remember(
            $this->cache->productKey($slug),
            function () use ($slug): array {
                $responsePayload = $this->fetchJson('marketplace/products/'.$this->encodePathSegment($slug));

                return is_array($responsePayload['data'] ?? null)
                    ? $responsePayload['data']
                    : $responsePayload;
            },
        );

        return MarketplaceProduct::fromArray($payload);
    }

    /**
     * List published versions for a marketplace product.
     */
    public function listVersions(string $productSlug): MarketplaceVersionList
    {
        $slug = $this->assertSlug($productSlug);

        /** @var array<string, mixed> $payload */
        $payload = $this->cache->remember(
            $this->cache->versionsKey($slug),
            fn (): array => $this->fetchJson(
                'marketplace/products/'.$this->encodePathSegment($slug).'/versions',
            ),
        );

        return MarketplaceVersionList::fromResponse($payload);
    }

    /**
     * Fetch a single published version for a marketplace product.
     */
    public function getVersion(string $productSlug, string $version): MarketplaceProductVersion
    {
        $slug = $this->assertSlug($productSlug);
        $version = trim($version);

        if ($version === '') {
            throw new \InvalidArgumentException('Marketplace version cannot be empty.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $this->cache->remember(
            $this->cache->versionKey($slug, $version),
            function () use ($slug, $version): array {
                $responsePayload = $this->fetchJson(
                    'marketplace/products/'
                    .$this->encodePathSegment($slug)
                    .'/versions/'
                    .$this->encodePathSegment($version),
                );

                return is_array($responsePayload['data'] ?? null)
                    ? $responsePayload['data']
                    : $responsePayload;
            },
        );

        return MarketplaceProductVersion::fromArray($payload);
    }

    /**
     * Drop the local catalogue cache (all entries).
     */
    public function flushCache(): void
    {
        $this->cache->flush();
    }

    /**
     * Drop cached payloads for one product (detail + versions).
     */
    public function forgetProductCache(string $productSlug): void
    {
        $slug = $this->assertSlug($productSlug);

        $this->cache->forget($this->cache->productKey($slug));
        $this->cache->forget($this->cache->versionsKey($slug));
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private function fetchJson(string $path, array $query = []): array
    {
        return $this->json($this->get($path, $query));
    }

    /**
     * @param  array<string, mixed>  $query
     */
    private function get(string $path, array $query = []): Response
    {
        try {
            $request = $this->httpClient();
            $url = $this->endpoint($path);

            $response = $query === []
                ? $request->get($url)
                : $request->get($url, $query);
        } catch (ConnectionException $exception) {
            throw MarketplaceApiException::connectionFailed($exception->getMessage(), $exception);
        } catch (Throwable $exception) {
            if ($this->isSslCertificateError($exception->getMessage())) {
                throw MarketplaceApiException::connectionFailed($exception->getMessage(), $exception);
            }

            throw $exception;
        }

        if ($response->successful()) {
            return $response;
        }

        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];
        $retryAfterHeader = is_numeric($response->header('Retry-After'))
            ? (int) $response->header('Retry-After')
            : null;

        throw MarketplaceApiException::fromResponse($json, $response->status(), $retryAfterHeader);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(Response $response): array
    {
        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     category?: string|null,
     *     price?: string|null,
     *     product_type?: string|null,
     *     sort?: string|null,
     *     page?: int|null,
     *     per_page?: int|null
     * }  $filters
     * @return array<string, int|string>
     */
    private function catalogQuery(array $filters): array
    {
        $query = [];

        foreach (['q', 'category', 'price', 'product_type', 'sort'] as $key) {
            $value = $filters[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $query[$key] = trim($value);
            }
        }

        if (isset($filters['page']) && is_numeric($filters['page']) && (int) $filters['page'] > 0) {
            $query['page'] = (int) $filters['page'];
        }

        if (isset($filters['per_page']) && is_numeric($filters['per_page']) && (int) $filters['per_page'] > 0) {
            $query['per_page'] = min(100, (int) $filters['per_page']);
        }

        return $query;
    }

    private function assertSlug(string $productSlug): string
    {
        $slug = trim($productSlug);

        if ($slug === '') {
            throw new \InvalidArgumentException('Marketplace product slug cannot be empty.');
        }

        return $slug;
    }

    private function encodePathSegment(string $value): string
    {
        return rawurlencode($value);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('corepanel.org.api_url'), '/').'/'.ltrim($path, '/');
    }

    private function httpClient(): PendingRequest
    {
        $client = Http::acceptJson()
            ->asJson()
            ->withToken($this->apiToken())
            ->timeout((int) config('corepanel.org.timeout_seconds', 10));

        $caBundle = $this->normalizedCaBundlePath();

        if ($caBundle !== null) {
            return $client->withOptions(['verify' => $caBundle]);
        }

        if ($this->shouldSkipSslVerification()) {
            return $client->withoutVerifying();
        }

        return $client;
    }

    private function apiToken(): string
    {
        $token = config('corepanel.org.api_token');

        if (is_string($token) && trim($token) !== '') {
            return trim($token);
        }

        return MarketplaceCatalogCredentials::TOKEN;
    }

    private function shouldSkipSslVerification(): bool
    {
        if (app()->environment('local', 'testing')) {
            return true;
        }

        return ! (bool) config('corepanel.org.verify_ssl', true);
    }

    private function normalizedCaBundlePath(): ?string
    {
        $caBundle = config('corepanel.org.ca_bundle');

        if (! is_string($caBundle) || trim($caBundle) === '') {
            return null;
        }

        $path = str_replace('\\', '/', trim($caBundle));

        if (! is_file($path)) {
            return null;
        }

        return $path;
    }

    private function isSslCertificateError(string $message): bool
    {
        return str_contains($message, 'SSL certificate problem')
            || str_contains($message, 'unable to get local issuer certificate')
            || str_contains($message, 'cURL error 60');
    }
}
