<?php

namespace App\Http\Requests\Admin;

use Core\Products\DataTransferObjects\ProductCategoryData;
use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(ProductCategoryStatus::values())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function categoryData(): ProductCategoryData
    {
        return ProductCategoryData::fromArray($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'parent_id' => filled($this->input('parent_id')) ? $this->input('parent_id') : null,
            'slug' => filled($this->input('slug')) ? $this->input('slug') : null,
        ]);
    }
}
