<?php

namespace App\Http\Requests\Admin;

use Core\KnowledgeBase\DataTransferObjects\KbCategoryData;
use Core\KnowledgeBase\Enums\KbCategoryStatus;
use Core\KnowledgeBase\Models\KbCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKbCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', KbCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(KbCategoryStatus::values())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function categoryData(): KbCategoryData
    {
        return KbCategoryData::fromArray($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => filled($this->input('slug')) ? $this->input('slug') : null,
            'description' => filled($this->input('description')) ? $this->input('description') : null,
        ]);
    }
}
