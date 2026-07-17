<?php

namespace Core\Orders\Services;

use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Core\Orders\Models\Cart;
use Core\Orders\Models\Order;

/**
 * Thin facade for cart → order conversion (roadmap 10.8 / 11.3).
 * Lifecycle transitions live on OrderService.
 */
class OrderConversionService
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }

    public function convertFromCheckout(Cart $cart, CheckoutDraftData $draft): Order
    {
        return $this->orderService->createFromCheckout($cart, $draft);
    }
}
