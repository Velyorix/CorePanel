<?php

namespace App\Http\Requests\Admin;

use Core\Orders\DataTransferObjects\CartItemData;
use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Core\Orders\Models\Order;
use Core\Orders\Services\CheckoutDraftService;
use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Core\Products\Services\ConfiguratorService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class StoreAdminOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $enabledMethods = app(CheckoutDraftService::class)->enabledPaymentMethodKeys();

        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:64'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'postal_code' => ['required', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:64'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'payment_method' => ['required', 'string', Rule::in($enabledMethods)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'submit_as_pending' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.billing_cycle' => ['required', 'string', Rule::in(BillingCycle::values())],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.custom_interval_days' => ['nullable', 'integer', 'min:1'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.addons' => ['nullable', 'array'],
            'items.*.addons.*' => ['string', 'max:100'],
        ];
    }

    public function draftData(): CheckoutDraftData
    {
        return CheckoutDraftData::fromArray($this->validated());
    }

    /**
     * @return list<CartItemData>
     */
    public function cartItems(): array
    {
        $configurator = app(ConfiguratorService::class);
        $items = [];

        foreach ($this->validated('items') as $index => $rawItem) {
            $product = Product::query()->with(['options', 'addons', 'pricing'])->findOrFail(
                (int) $rawItem['product_id'],
            );

            try {
                $items[] = $configurator->buildCartItem($product, $rawItem);
            } catch (InvalidArgumentException $exception) {
                throw ValidationException::withMessages([
                    "items.{$index}.product_id" => $exception->getMessage(),
                ]);
            }
        }

        return $items;
    }

    public function submitAsPending(): bool
    {
        return $this->boolean('submit_as_pending');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => filled($this->input('country'))
                ? strtoupper((string) $this->input('country'))
                : null,
            'coupon_code' => filled($this->input('coupon_code'))
                ? trim((string) $this->input('coupon_code'))
                : null,
            'submit_as_pending' => $this->boolean('submit_as_pending'),
        ]);
    }
}
