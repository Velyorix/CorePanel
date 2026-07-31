<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Core\License\DataTransferObjects\LicenseEntitlement;
use Core\License\Services\EntitlementService;
use Core\Marketplace\DataTransferObjects\MarketplaceProduct;
use Core\Marketplace\Enums\MarketplaceInstallAction;
use Core\Marketplace\Exceptions\MarketplaceApiException;
use Core\Marketplace\Services\MarketplaceClient;
use Core\Marketplace\Services\MarketplaceCompatibilityGuard;
use Core\Marketplace\Services\MarketplaceEntitlementGuard;
use Core\Permissions\Services\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class MarketplaceController extends Controller
{
    public function __construct(
        private readonly MarketplaceClient $marketplace,
        private readonly MarketplaceEntitlementGuard $entitlements,
        private readonly MarketplaceCompatibilityGuard $compatibility,
        private readonly EntitlementService $licenseEntitlements,
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

        return view('client.marketplace.index', [
            'products' => $products,
            'paginator' => $paginator,
            'filters' => $filters,
            'catalogError' => $catalogError,
            'pageTitle' => $this->indexTitle($filters['product_type']),
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

        return view('client.marketplace.show', [
            'product' => $this->presentProduct($item, $versions),
        ]);
    }

    public function purchases(Request $request): View
    {
        $this->ensureCanView($request);
        $this->ensureMarketplaceEnabled();

        $purchases = array_map(
            fn (LicenseEntitlement $entitlement): array => $this->presentPurchase($entitlement),
            array_values(array_filter(
                $this->licenseEntitlements->all(),
                static fn (LicenseEntitlement $entitlement): bool => in_array(
                    $entitlement->productType,
                    ['module', 'theme'],
                    true,
                ),
            )),
        );

        return view('client.marketplace.purchases', [
            'purchases' => $purchases,
            'isLicensed' => $this->licenseEntitlements->isLicensed(),
        ]);
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
            'install_action' => $decision->action->value,
            'owned' => $this->isOwned($product),
            'purchase_url' => $this->purchaseUrl($product->slug),
        ];
    }

    /**
     * @param  list<\Core\Marketplace\DataTransferObjects\MarketplaceProductVersion>  $versions
     * @return array<string, mixed>
     */
    private function presentProduct(MarketplaceProduct $product, array $versions): array
    {
        $decision = $this->entitlements->evaluate($product);
        $owned = $this->isOwned($product);

        $presentedVersions = [];

        foreach ($versions as $version) {
            $presentedVersions[] = [
                'version' => $version->version,
                'is_latest' => $version->isLatest,
                'has_archive' => $version->hasArchive,
                'compatible' => $this->compatibility->isCompatible($version, $product),
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
            'install_action' => $decision->action->value,
            'entitlement_message' => $decision->message,
            'owned' => $owned,
            'can_purchase' => $decision->action === MarketplaceInstallAction::Purchase,
            'purchase_url' => $this->purchaseUrl($product->slug),
            'versions' => $presentedVersions,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentPurchase(LicenseEntitlement $entitlement): array
    {
        return [
            'product_type' => $entitlement->productType,
            'product_sku' => $entitlement->productSku,
            'product_name' => $entitlement->productName,
            'granted_at' => $entitlement->grantedAt,
            'purchase_url' => $this->purchaseUrl($entitlement->productSku),
        ];
    }

    private function isOwned(MarketplaceProduct $product): bool
    {
        if ($product->isModule()) {
            return $this->licenseEntitlements->hasModule($product->sku)
                || $this->licenseEntitlements->hasModule($product->slug);
        }

        if ($product->isTheme()) {
            return $this->licenseEntitlements->hasTheme($product->sku)
                || $this->licenseEntitlements->hasTheme($product->slug);
        }

        return $this->licenseEntitlements->has($product->sku)
            || $this->licenseEntitlements->has($product->slug);
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

    private function purchaseUrl(string $slugOrSku): string
    {
        $base = rtrim((string) config('corepanel.org.store_url', 'https://corepanel.org'), '/');

        return $base.'/marketplace/'.rawurlencode(trim($slugOrSku));
    }

    private function indexTitle(?string $productType): string
    {
        return match ($productType) {
            'module' => __('Marketplace modules'),
            'theme' => __('Marketplace themes'),
            default => __('Marketplace'),
        };
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

        if ($user === null || ! $this->permissions->userHasPermission($user, 'client.marketplace.view')) {
            abort(403, __('You do not have permission to browse the marketplace.'));
        }
    }
}
