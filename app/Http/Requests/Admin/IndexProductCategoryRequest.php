<?php

namespace App\Http\Requests\Admin;

use Core\Products\Enums\ProductCategoryStatus;
use Core\Products\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ProductCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ProductCategoryStatus::values())],
            'sort' => ['nullable', 'string', Rule::in(['name', 'slug', 'status', 'sort_order', 'created_at'])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     status: ProductCategoryStatus|null,
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
                ? ProductCategoryStatus::tryFrom((string) $validated['status'])
                : null,
            'sort' => (string) ($validated['sort'] ?? 'sort_order'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
        ];
    }
}
