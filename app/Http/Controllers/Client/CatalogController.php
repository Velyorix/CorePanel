<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ConfigureProductRequest;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Orders\Services\CartService;
use Core\Products\Services\CatalogService;
use Core\Products\Services\ConfiguratorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService,
        private readonly ConfiguratorService $configuratorService,
        private readonly CartService $cartService,
    ) {
    }

    public function index(): View
    {
        $categories = $this->catalogService->listActiveCategories();

        return view('client.catalog.index', [
            'categories' => $categories,
        ]);
    }

    public function category(string $category): View
    {
        $resolved = $this->catalogService->findActiveCategoryBySlug($category);
        $products = $this->catalogService->paginatePublishedProductsInCategory($resolved);

        return view('client.catalog.category', [
            'category' => $resolved,
            'products' => $products,
        ]);
    }

    public function show(string $product): View
    {
        $resolved = $this->catalogService->findPublishedProductBySlug($product);

        return view('client.catalog.show', [
            'product' => $resolved,
        ]);
    }

    public function configure(string $product): View
    {
        $resolved = $this->catalogService->findPublishedProductForConfigure($product);

        return view('client.catalog.configure', [
            'product' => $resolved,
            'billingCycles' => $resolved->enabledBillingCycles(),
        ]);
    }

    public function store(ConfigureProductRequest $request, string $product): RedirectResponse
    {
        $resolved = $request->catalogProduct();

        try {
            $itemData = $this->configuratorService->buildCartItem(
                $resolved,
                $request->configuratorInput(),
            );

            $client = $this->resolveClient($request);
            $cart = $this->cartService->getOrCreate(
                $client,
                $client === null ? $request->session()->getId() : null,
            );

            $this->cartService->addItem($cart, $itemData);
        } catch (InvalidArgumentException $exception) {
            return back()
                ->withInput()
                ->withErrors(['configurator' => $exception->getMessage()]);
        }

        return redirect()
            ->route('client.catalog.products.show', $resolved->slug)
            ->with('status', __('Product added to your cart.'));
    }

    private function resolveClient(Request $request): ?Client
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        return $user->clients()->orderBy('clients.id')->first()
            ?? $user->ownedClients()->orderBy('id')->first();
    }
}
