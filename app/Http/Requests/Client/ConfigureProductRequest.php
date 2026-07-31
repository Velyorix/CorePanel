<?php

namespace App\Http\Requests\Client;

use Core\Products\Enums\BillingCycle;
use Core\Products\Models\Product;
use Core\Products\Services\CatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConfigureProductRequest extends FormRequest
{
    private ?Product $catalogProduct = null;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function catalogProduct(): Product
    {
        return $this->catalogProduct ??= app(CatalogService::class)
            ->findPublishedProductForConfigure((string) $this->route('product'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $product = $this->catalogProduct();
        $enabledCycles = $product->enabledBillingCycles();

        return [
            'billing_cycle' => [
                'required',
                'string',
                Rule::in(array_map(
                    static fn (BillingCycle $cycle): string => $cycle->value,
                    $enabledCycles,
                )),
            ],
            'custom_interval_days' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['nullable', 'integer', 'min:1'],
            'options' => ['nullable', 'array'],
            'addons' => ['nullable', 'array'],
            'addons.*' => ['string', 'max:100'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $cycle = BillingCycle::tryFrom((string) $this->input('billing_cycle'));

            if ($cycle?->requiresCustomInterval() && blank($this->input('custom_interval_days'))) {
                $product = $this->catalogProduct();
                $pricing = $product->pricingFor($cycle);

                if ($pricing?->custom_interval_days === null) {
                    $validator->errors()->add(
                        'custom_interval_days',
                        __('Custom billing cycles require custom_interval_days.'),
                    );
                }
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function configuratorInput(): array
    {
        return $this->validated();
    }
}
