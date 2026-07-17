<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\BuildsProductFormData;
use Core\Products\DataTransferObjects\ProductData;
use Core\Products\Enums\BillingCycle;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use BuildsProductFormData;

    public function authorize(): bool
    {
        /** @var Product $product */
        $product = $this->route('product');

        return $this->user()?->can('update', $product) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $pricingRules = [
            'pricing' => ['nullable', 'array'],
        ];

        foreach (BillingCycle::values() as $cycle) {
            $pricingRules["pricing.{$cycle}.enabled"] = ['nullable', 'boolean'];
            $pricingRules["pricing.{$cycle}.price"] = ['nullable', 'numeric', 'min:0'];
            $pricingRules["pricing.{$cycle}.setup_fee"] = ['nullable', 'numeric', 'min:0'];
            $pricingRules["pricing.{$cycle}.custom_interval_days"] = ['nullable', 'integer', 'min:1'];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'type' => ['required', 'string', Rule::in(ProductType::values())],
            'module' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            ...$pricingRules,
        ];
    }

    public function productData(): ProductData
    {
        /** @var Product $product */
        $product = $this->route('product');
        $validated = $this->validated();

        $payload = [
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? null,
            'category_id' => $validated['category_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'type' => $validated['type'],
            'module' => $validated['module'] ?? null,
            'module_capabilities' => $product->requiredCapabilities(),
            'status' => $product->status->value,
            'sort_order' => $validated['sort_order'] ?? 0,
            'pricing' => $this->pricingPayloadFromRequest(),
            'options' => $this->existingOptionsPayload($product),
            'addons' => $this->existingAddonsPayload($product),
        ];

        $rules = $this->existingProvisioningRulesPayload($product);

        if ($rules !== null) {
            $payload['provisioning_rules'] = $rules;
        }

        return ProductData::fromArray($payload);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => filled($this->input('category_id')) ? $this->input('category_id') : null,
            'module' => filled($this->input('module')) ? $this->input('module') : null,
            'slug' => filled($this->input('slug')) ? $this->input('slug') : null,
        ]);
    }
}
