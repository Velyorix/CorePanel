<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\ApplyCheckoutCouponRequest;
use App\Http\Requests\Client\SubmitCheckoutRequest;
use Core\Auth\Models\User;
use Core\Billing\DataTransferObjects\TaxAddress;
use Core\Billing\Exceptions\InvalidCouponException;
use Core\Billing\Services\CouponService;
use Core\Clients\DataTransferObjects\ClientData;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Core\Clients\Services\ClientService;
use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Core\Orders\Models\Cart;
use Core\Orders\Models\Order;
use Core\Orders\Services\CartService;
use Core\Orders\Services\CartSummary;
use Core\Orders\Services\CheckoutDraftService;
use Core\Orders\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cartService,
        private readonly CartSummary $cartSummary,
        private readonly CheckoutDraftService $checkoutDraftService,
        private readonly ClientService $clientService,
        private readonly OrderService $orderService,
        private readonly CouponService $couponService,
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
            'summary' => $this->cartSummary->summarize(
                $cart,
                $client !== null ? TaxAddress::fromClient($client) : null,
                $draft->couponCode,
            ),
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
            $this->syncClientBilling($client, $draft);
        }

        return redirect()
            ->route('client.checkout.complete')
            ->with('status', __('Checkout details saved. Review and place your order.'));
    }

    public function applyCoupon(ApplyCheckoutCouponRequest $request): RedirectResponse
    {
        if (! $this->checkoutDraftService->isCouponUiEnabled()) {
            return back()->withErrors(['coupon_code' => __('Coupons are not available.')]);
        }

        $user = $request->user();
        $client = $this->resolveClient($request);
        $cart = $this->resolveCart($request);
        $code = $request->validated('coupon_code');

        if (filled($code) && $client !== null) {
            try {
                $coupon = $this->couponService->findByCode((string) $code);

                if ($coupon === null) {
                    throw new InvalidCouponException(__('Invalid coupon code.'));
                }

                $this->couponService->validateForOrder($coupon, $client, $cart);
            } catch (InvalidCouponException $exception) {
                return back()
                    ->withInput()
                    ->withErrors(['coupon_code' => $exception->getMessage()]);
            }
        }

        $existing = $this->checkoutDraftService->load($request->session());
        $draft = $this->checkoutDraftService
            ->prefill($client, $user instanceof User ? $user : null, $existing)
            ->withCouponCode($code);

        $this->checkoutDraftService->store($request->session(), $draft);

        return redirect()
            ->route('client.checkout.index')
            ->with('status', __('Coupon applied.'));
    }

    public function complete(Request $request): View|RedirectResponse
    {
        $draft = $this->checkoutDraftService->load($request->session());

        if ($draft === null) {
            return redirect()
                ->route('client.checkout.index');
        }

        $cart = $this->resolveCart($request);

        if ($cart->isEmpty() || ! $cart->isOpen()) {
            return redirect()
                ->route('client.cart.index')
                ->withErrors(['cart' => __('Your cart is no longer available for checkout.')]);
        }

        return view('client.checkout.complete', [
            'draft' => $draft,
            'cart' => $cart,
            'summary' => $this->cartSummary->summarize(
                $cart,
                TaxAddress::fromDraft($draft),
                $draft->couponCode,
            ),
        ]);
    }

    public function placeOrder(Request $request): RedirectResponse
    {
        $draft = $this->checkoutDraftService->load($request->session());

        if ($draft === null) {
            return redirect()
                ->route('client.checkout.index')
                ->withErrors(['checkout' => __('Save your checkout details before placing the order.')]);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return redirect()->route('login');
        }

        try {
            $client = $this->ensureClient($user, $draft);
            $cart = $this->resolveCartForClient($request, $client);

            if ($cart->isEmpty()) {
                return redirect()
                    ->route('client.cart.index')
                    ->withErrors(['cart' => __('Add items to your cart before placing an order.')]);
            }

            $order = $this->orderService->createFromCheckout($cart, $draft);
            $this->checkoutDraftService->clear($request->session());
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return redirect()
                ->route('client.checkout.complete')
                ->withErrors(['checkout' => $exception->getMessage()]);
        }

        return redirect()
            ->route('client.checkout.placed', $order)
            ->with('status', __('Your order has been placed and is pending payment.'));
    }

    public function placed(Request $request, Order $order): View|RedirectResponse
    {
        $client = $this->resolveClient($request);

        if ($client === null || (int) $order->client_id !== (int) $client->id) {
            abort(404);
        }

        $order->loadMissing(['items.product']);

        return view('client.checkout.placed', [
            'order' => $order,
            'summary' => [
                'item_count' => $order->items->sum('quantity'),
                'recurring_subtotal' => $order->subtotal_recurring,
                'setup_subtotal' => $order->subtotal_setup,
                'first_payment_subtotal' => number_format(
                    (float) $order->subtotal_recurring + (float) $order->subtotal_setup,
                    2,
                    '.',
                    '',
                ),
                'discount_amount' => $order->discount_amount ?? '0.00',
                'coupon_code' => $order->coupon_code,
                'tax_label' => __('Tax (estimate)'),
                'first_payment_tax' => $order->tax_amount,
                'first_payment_total' => $order->total_amount,
                'tax_is_estimate' => true,
            ],
        ]);
    }

    private function ensureClient(User $user, CheckoutDraftData $draft): Client
    {
        $client = $user->clients()->orderBy('clients.id')->first()
            ?? $user->ownedClients()->orderBy('id')->first();

        if ($client !== null) {
            $this->syncClientBilling($client, $draft);

            return $client->fresh() ?? $client;
        }

        return $this->clientService->create(ClientData::fromArray([
            'user_id' => $user->id,
            'company_name' => $draft->companyName,
            'vat_number' => $draft->vatNumber,
            'address' => $draft->address,
            'city' => $draft->city,
            'country' => $draft->country,
            'postal_code' => $draft->postalCode,
            'phone' => $draft->phone,
            'status' => ClientStatus::Active->value,
        ]));
    }

    private function syncClientBilling(Client $client, CheckoutDraftData $draft): void
    {
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

    private function resolveCart(Request $request): Cart
    {
        $client = $this->resolveClient($request);

        return $this->resolveCartForClient($request, $client);
    }

    private function resolveCartForClient(Request $request, ?Client $client): Cart
    {
        if ($client !== null) {
            $sessionId = $request->session()->getId();

            if (filled($sessionId)) {
                return $this->cartService->mergeSessionCartIntoClient($sessionId, $client);
            }

            return $this->cartService->getOrCreate($client, null);
        }

        return $this->cartService->getOrCreate(null, $request->session()->getId());
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
