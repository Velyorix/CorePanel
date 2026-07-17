<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\UpdateCartItemQuantityRequest;
use Core\Auth\Models\User;
use Core\Clients\Models\Client;
use Core\Orders\Models\Cart;
use Core\Orders\Models\CartItem;
use Core\Orders\Services\CartService;
use Core\Orders\Services\CartSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CartSummary $cartSummary,
    ) {
    }

    public function index(Request $request): View
    {
        $cart = $this->resolveCart($request);
        $summary = $this->cartSummary->summarize($cart);

        return view('client.cart.index', [
            'cart' => $cart,
            'summary' => $summary,
        ]);
    }

    public function updateQuantity(UpdateCartItemQuantityRequest $request, CartItem $item): RedirectResponse
    {
        $cart = $this->resolveCart($request);

        try {
            $this->assertItemBelongsToCart($cart, $item);
            $this->cartService->updateQuantity($cart, $item, (int) $request->validated('quantity'));
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }

        return redirect()
            ->route('client.cart.index')
            ->with('status', __('Cart updated.'));
    }

    public function destroyItem(Request $request, CartItem $item): RedirectResponse
    {
        $cart = $this->resolveCart($request);

        try {
            $this->assertItemBelongsToCart($cart, $item);
            $this->cartService->removeItem($cart, $item);
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }

        return redirect()
            ->route('client.cart.index')
            ->with('status', __('Item removed from cart.'));
    }

    public function clear(Request $request): RedirectResponse
    {
        $cart = $this->resolveCart($request);

        try {
            $this->cartService->clear($cart);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['cart' => $exception->getMessage()]);
        }

        return redirect()
            ->route('client.cart.index')
            ->with('status', __('Cart cleared.'));
    }

    private function resolveCart(Request $request): Cart
    {
        $client = $this->resolveClient($request);

        return $this->cartService->getOrCreate(
            $client,
            $client === null ? $request->session()->getId() : null,
        );
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

    private function assertItemBelongsToCart(Cart $cart, CartItem $item): void
    {
        if ((int) $item->cart_id !== (int) $cart->id) {
            throw new InvalidArgumentException('The cart item does not belong to your cart.');
        }
    }
}
