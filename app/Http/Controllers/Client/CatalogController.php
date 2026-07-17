<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Core\Products\Services\CatalogService;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalogService,
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
}
