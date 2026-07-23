<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Marketplace\DataTransferObjects\MarketplaceProduct;
use Core\Marketplace\Exceptions\MarketplaceApiException;
use Core\Marketplace\Exceptions\MarketplaceCompatibilityException;
use Core\Marketplace\Exceptions\MarketplaceEntitlementException;
use Core\Marketplace\Exceptions\MarketplaceInstallException;
use Core\Marketplace\Services\MarketplaceClient;
use Core\Marketplace\Services\MarketplaceCompatibilityGuard;
use Core\Marketplace\Services\MarketplaceEntitlementGuard;
use Core\Marketplace\Services\MarketplaceInstaller;
use Core\Marketplace\Services\MarketplacePackageOriginStore;
use Core\Marketplace\Services\MarketplaceUpdateChecker;
use Core\Permissions\Services\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Throwable;

class MarketplaceController extends Controller
{
    public function __construct(
        private readonly MarketplaceClient $marketplace,
        private readonly MarketplaceInstaller $installer,
        private readonly MarketplaceEntitlementGuard $entitlements,
        private readonly MarketplaceCompatibilityGuard $compatibility,
        private readonly MarketplaceUpdateChecker $updates,
        private readonly MarketplacePackageOriginStore $origins,
        private readonly PermissionService $permissions,
    ) {
    }

    public function index(Request $request): View
    {
        $this->ensureCanView($request);
        $this->ensureMarketplaceEnabled();

        $filters = $this->catalogFilters($request);
        $catalogError = null;
        $products = [];
        $paginator = null;

        try {
            $page = $this->marketplace->listProducts($filters);
            $products = array_map(
                fn (MarketplaceProduct $product): array => $this->presentCatalogItem($product),
                $page->items,
            );

            $paginator = new LengthAwarePaginator(
                items: $products,
                total: $page->total(),
                perPage: max(1, $page->meta['per_page']),
                currentPage: $page->currentPage(),
                options: [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ],
            );
        } catch (MarketplaceApiException $exception) {
            $catalogError = $exception->getMessage();
            $paginator = new LengthAwarePaginator(
                items: [],
                total: 0,
                perPage: $filters['per_page'],
                currentPage: 1,
                options: [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ],
            );
        }

        return view('admin.marketplace.index', [
            'products' => $products,
            'paginator' => $paginator,
            'filters' => $filters,
            'catalogError' => $catalogError,
            'canManage' => $this->canManage($request),
            'updatesCount' => count($this->updates->availableUpdates()),
        ]);
    }

    public function show(Request $request, string $product): View
    {
        $this->ensureCanView($request);
        $this->ensureMarketplaceEnabled();

        try {
            $item = $this->marketplace->getProduct($product);
            $versions = $this->marketplace->listVersions($product)->versions;
        } catch (MarketplaceApiException $exception) {
            abort(404, $exception->getMessage());
        }

        return view('admin.marketplace.show', [
            'product' => $this->presentProduct($item, $versions),
            'canManage' => $this->canManage($request),
        ]);
    }

    public function install(Request $request, string $product): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureMarketplaceEnabled();

        $validated = $request->validate([
            'version' => ['nullable', 'string', 'max:64'],
            'enable' => ['sometimes', 'boolean'],
        ]);

        $version = isset($validated['version']) && trim((string) $validated['version']) !== ''
            ? trim((string) $validated['version'])
            : null;
        $enable = (bool) ($validated['enable'] ?? false);

        try {
            $result = $this->installer->install($product, $version, $enable);
        } catch (
            MarketplaceEntitlementException|
            MarketplaceCompatibilityException|
            MarketplaceInstallException|
            MarketplaceApiException $exception
        ) {
            return redirect()
                ->route('admin.marketplace.show', $product)
                ->withErrors(['marketplace' => $exception->getMessage()]);
        } catch (Throwable) {
            return redirect()
                ->route('admin.marketplace.show', $product)
                ->withErrors(['marketplace' => __('Unable to install this marketplace package.')]);
        }

        return redirect()
            ->route('admin.marketplace.show', $product)
            ->with(
                'status',
                __('Package :name installed successfully (:version).', [
                    'name' => $result->packageKey,
                    'version' => $result->version,
                ]),
            );
    }

    public function updates(Request $request): View
    {
        $this->ensureCanView($request);
        $this->ensureMarketplaceEnabled();

        $result = $this->updates->latestResult();

        return view('admin.marketplace.updates', [
            'updates' => $result?->updates ?? [],
            'checkedAt' => $result?->checkedAt,
            'checkedCount' => $result?->checkedCount ?? 0,
            'canManage' => $this->canManage($request),
        ]);
    }

    public function checkUpdates(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureMarketplaceEnabled();

        if (! (bool) config('corepanel.marketplace.updates.enabled', true)) {
            return redirect()
                ->route('admin.marketplace.updates')
                ->withErrors(['marketplace' => __('Marketplace update checks are disabled.')]);
        }

        try {
            $result = $this->updates->check();
        } catch (Throwable $exception) {
            return redirect()
                ->route('admin.marketplace.updates')
                ->withErrors(['marketplace' => $exception->getMessage()]);
        }

        $count = count($result->updates);

        return redirect()
            ->route('admin.marketplace.updates')
            ->with(
                'status',
                $count === 0
                    ? __('Update check completed. No updates available.')
                    : __('Update check completed. :count update(s) available.', ['count' => $count]),
            );
    }

    /**
     * @return array{
     *     q: string|null,
     *     category: string|null,
     *     price: string|null,
     *     product_type: string|null,
     *     sort: string|null,
     *     page: int,
     *     per_page: int
     * }
     */
    private function catalogFilters(Request $request): array
    {
        $string = static function (mixed $value): ?string {
            if (! is_string($value)) {
                return null;
            }

            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        };

        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 20)));

        $productType = $string($request->query('product_type'));
        if ($productType !== null && ! in_array($productType, ['module', 'theme'], true)) {
            $productType = null;
        }

        $price = $string($request->query('price'));
        if ($price !== null && ! in_array($price, ['free', 'paid'], true)) {
            $price = null;
        }

        $sort = $string($request->query('sort'));
        if ($sort !== null && ! in_array($sort, ['downloads', 'rating', 'price_asc', 'price_desc'], true)) {
            $sort = null;
        }

        return [
            'q' => $string($request->query('q')),
            'category' => $string($request->query('category')),
            'price' => $price,
            'product_type' => $productType,
            'sort' => $sort,
            'page' => $page,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentCatalogItem(MarketplaceProduct $product): array
    {
        $decision = $this->entitlements->evaluate($product);
        $origin = $this->originForSlug($product->slug);

        return [
            'slug' => $product->slug,
            'sku' => $product->sku,
            'name' => $product->name,
            'short_description' => $product->shortDescription,
            'product_type' => $product->productType,
            'category' => (string) ($product->category['name'] ?? ''),
            'developer' => (string) ($product->developer['name'] ?? ''),
            'is_free' => $product->isFree(),
            'price_label' => $this->priceLabel($product),
            'current_version' => $product->currentVersion,
            'is_vip' => $product->isVip,
            'thumbnail_url' => $product->thumbnailUrl,
            'can_install' => $decision->canInstall() && $origin === null,
            'install_action' => $decision->action->value,
            'locally_installed' => $origin !== null,
            'local_package_key' => $origin['package_key'] ?? null,
        ];
    }

    /**
     * @param  list<\Core\Marketplace\DataTransferObjects\MarketplaceProductVersion>  $versions
     * @return array<string, mixed>
     */
    private function presentProduct(MarketplaceProduct $product, array $versions): array
    {
        $decision = $this->entitlements->evaluate($product);
        $origin = $this->originForSlug($product->slug);

        $presentedVersions = [];

        foreach ($versions as $version) {
            $compatible = $this->compatibility->isCompatible($version, $product);

            $presentedVersions[] = [
                'version' => $version->version,
                'is_latest' => $version->isLatest,
                'has_archive' => $version->hasArchive,
                'compatible' => $compatible,
                'min_cms_version' => $version->minCmsVersion(),
                'max_cms_version' => $version->maxCmsVersion(),
                'changelog' => $version->changelog,
                'published_at' => $version->publishedAt,
            ];
        }

        return [
            'slug' => $product->slug,
            'sku' => $product->sku,
            'name' => $product->name,
            'short_description' => $product->shortDescription,
            'product_type' => $product->productType,
            'category' => (string) ($product->category['name'] ?? ''),
            'developer' => (string) ($product->developer['name'] ?? ''),
            'is_free' => $product->isFree(),
            'price_label' => $this->priceLabel($product),
            'current_version' => $product->currentVersion,
            'is_vip' => $product->isVip,
            'thumbnail_url' => $product->thumbnailUrl,
            'compatibility' => $product->compatibility,
            'can_install' => $decision->canInstall() && $origin === null,
            'install_action' => $decision->action->value,
            'entitlement_message' => $decision->message,
            'locally_installed' => $origin !== null,
            'local_package_key' => $origin['package_key'] ?? null,
            'versions' => $presentedVersions,
        ];
    }

    private function priceLabel(MarketplaceProduct $product): string
    {
        if ($product->isFree()) {
            return __('Free');
        }

        $amount = $product->pricing['amount'] ?? null;
        $currency = strtoupper((string) ($product->pricing['currency'] ?? 'EUR'));

        if (! is_numeric($amount)) {
            return __('Paid');
        }

        return number_format(((int) $amount) / 100, 2).' '.$currency;
    }

    /**
     * @return array{product_type: string, package_key: string, slug: string, sku: string|null}|null
     */
    private function originForSlug(string $slug): ?array
    {
        foreach ($this->origins->all() as $origin) {
            if ($origin['slug'] === $slug) {
                return $origin;
            }
        }

        return null;
    }

    private function ensureMarketplaceEnabled(): void
    {
        if (! (bool) config('corepanel.marketplace.enabled', true)) {
            abort(404);
        }
    }

    private function ensureCanView(Request $request): void
    {
        $user = $request->user();

        if ($user === null || ! $this->permissions->userHasPermission($user, 'marketplace.view')) {
            abort(403, __('You do not have permission to view the marketplace.'));
        }
    }

    private function ensureCanManage(Request $request): void
    {
        if (! $this->canManage($request)) {
            abort(403, __('You do not have permission to manage marketplace packages.'));
        }
    }

    private function canManage(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $this->permissions->userHasPermission($user, 'marketplace.manage');
    }
}
