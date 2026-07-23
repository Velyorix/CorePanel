<?php

namespace App\Http\Requests\Admin;

use Core\KnowledgeBase\DataTransferObjects\KbArticleData;
use Core\KnowledgeBase\Enums\KbArticleStatus;
use Core\KnowledgeBase\Models\KbArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKbArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', KbArticle::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:kb_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:100000'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function articleData(): KbArticleData
    {
        $validated = $this->validated();

        return KbArticleData::fromArray([
            ...$validated,
            'status' => KbArticleStatus::Draft->value,
            'author_id' => $this->user()?->id,
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'category_id' => filled($this->input('category_id')) ? $this->input('category_id') : null,
            'slug' => filled($this->input('slug')) ? $this->input('slug') : null,
            'excerpt' => filled($this->input('excerpt')) ? $this->input('excerpt') : null,
        ]);
    }
}
