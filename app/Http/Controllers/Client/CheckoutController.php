<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ApplyCheckoutCouponRequest;
use App\Http\Requests\Client\SubmitCheckoutRequest;
use Core\Auth\Models\User;
use Core\Clients\DataTransferObjects\ClientData;
use Core\Clients\Models\Client;
use Core\Clients\Services\ClientService;
use Core\Orders\Models\Cart;
use Core\Orders\Services\CartService;
use Core\Orders\Services\CartSummary;
use Core\Orders\Services\CheckoutDraftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CartSummary $cartSummary,
        private readonly CheckoutDraftService $checkoutDraftService,
        private readonly ClientService $clientService,
    ) {
    }

    public function index(Request $request): View|RedirectResponse
    {
        $cart = $this->resolveCart($request);

        if ($cart->isEmpty()) {
            return redirect()
                ->route('client.cart.index')
                ->withErrors(['cart' => __('Add items to your cart before checkout.')]);
        }

        $user = $request->user();
        $client = $this->resolveClient($request);
        $draft = $this->checkoutDraftService->prefill(
            $client,
            $user instanceof User ? $user : null,
            $this->checkoutDraftService->load($request->session()),
        );

        return view('client.checkout.index', [
            'cart' => $cart,
            'summary' => $this->cartSummary->summarize($cart),
            'draft' => $draft,
            'paymentMethods' => $this->checkoutDraftService->paymentMethods(),
            'couponEnabled' => $this->checkoutDraftService->isCouponUiEnabled(),
        ]);
    }

    public function store(SubmitCheckoutRequest $request): RedirectResponse
    {
        $cart = $this->resolveCart($request);

        if ($cart->isEmpty()) {
            return redirect()
                ->route('client.cart.index')
                ->withErrors(['cart' => __('Add items to your cart before checkout.')]);
        }

        $draft = $request->draftData();
        $this->checkoutDraftService->store($request->session(), $draft);

        $client = $this->resolveClient($request);

        if ($client !== null) {
            $this->clientService->update($client, ClientData::fromArray([
                'user_id' => $client->user_id,
                'company_name' => $draft->companyName,
                'vat_number' => $draft->vatNumber,
                'address' => $draft->address,
                'city' => $draft->city,
                'country' => $draft->country,
                'postal_code' => $draft->postalCode,
                'phone' => $draft->phone,
                'status' => $client->status->value,
            ]));
        }

        return redirect()
            ->route('client.checkout.complete')
            ->with('status', __('Checkout details saved. Your order will be placed in the next step.'));
    }

    public function applyCoupon(ApplyCheckoutCouponRequest $request): RedirectResponse
    {
        if (! $this->checkoutDraftService->isCouponUiEnabled()) {
            return back()->withErrors(['coupon_code' => __('Coupons are not available.')]);
        }

        $user = $request->user();
        $client = $this->resolveClient($request);
        $existing = $this->checkoutDraftService->load($request->session());
        $draft = $this->checkoutDraftService
            ->prefill($client, $user instanceof User ? $user : null, $existing)
            ->withCouponCode($request->validated('coupon_code'));

        $this->checkoutDraftService->store($request->session(), $draft);

        return redirect()
            ->route('client.checkout.index')
            ->with('status', __('Coupon saved. It will be validated when you place your order.'));
    }

    public function complete(Request $request): View|RedirectResponse
    {
        $draft = $this->checkoutDraftService->load($request->session());

        if ($draft === null) {
            return redirect()
                ->route('client.checkout.index');
        }

        $cart = $this->resolveCart($request);

        return view('client.checkout.complete', [
            'draft' => $draft,
            'cart' => $cart,
            'summary' => $this->cartSummary->summarize($cart),
        ]);
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
}
