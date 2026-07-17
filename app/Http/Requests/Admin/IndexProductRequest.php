<?php

namespace App\Http\Requests\Admin;

use Core\Products\Enums\ProductStatus;
use Core\Products\Enums\ProductType;
use Core\Products\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Product::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ProductStatus::values())],
            'type' => ['nullable', 'string', Rule::in(ProductType::values())],
            'category_id' => ['nullable', 'integer', 'exists:product_categories,id'],
            'sort' => ['nullable', 'string', Rule::in(['name', 'slug', 'type', 'status', 'sort_order', 'created_at'])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     status: ProductStatus|null,
     *     type: ProductType|null,
     *     category_id: int|null,
     *     sort: string,
     *     dir: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        $q = isset($validated['q']) ? trim((string) $validated['q']) : null;

        return [
            'q' => $q === '' ? null : $q,
            'status' => isset($validated['status'])
                ? ProductStatus::tryFrom((string) $validated['status'])
                : null,
            'type' => isset($validated['type'])
                ? ProductType::tryFrom((string) $validated['type'])
                : null,
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'sort' => (string) ($validated['sort'] ?? 'sort_order'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
        ];
    }
}
