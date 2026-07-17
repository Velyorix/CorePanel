<?php

namespace App\Http\Requests\Admin;

use Core\Products\DataTransferObjects\ProductCategoryData;
use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ProductCategory $category */
        $category = $this->route('productCategory');

        return $this->user()?->can('update', $category) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ProductCategory $category */
        $category = $this->route('productCategory');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:product_categories,id',
                Rule::notIn([$category->id]),
            ],
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
