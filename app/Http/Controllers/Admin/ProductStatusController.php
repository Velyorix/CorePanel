<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Core\Products\Models\Product;
use Core\Products\Services\ProductService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

class ProductStatusController extends Controller
{
    public function __construct(
        private readonly ProductService $productService,
    ) {
    }

    public function publish(Product $product): RedirectResponse
    {
        return $this->runTransition($product, 'publish', __('Product published successfully.'));
    }

    public function unpublish(Product $product): RedirectResponse
    {
        return $this->runTransition($product, 'unpublish', __('Product unpublished successfully.'));
    }

    public function archive(Product $product): RedirectResponse
    {
        return $this->runTransition($product, 'archive', __('Product archived successfully.'));
    }

    public function restore(Product $product): RedirectResponse
    {
        return $this->runTransition($product, 'restore', __('Product restored to draft successfully.'));
    }

    private function runTransition(Product $product, string $action, string $successMessage): RedirectResponse
    {
        Gate::authorize('update', $product);

        try {
            $this->productService->{$action}($product);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('admin.products.show', $product)
            ->with('status', $successMessage);
    }
}
