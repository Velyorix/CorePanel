<?php

namespace App\Http\Requests\Admin;

use Core\KnowledgeBase\DataTransferObjects\KbArticleData;
use Core\KnowledgeBase\Models\KbArticle;
use Illuminate\Foundation\Http\FormRequest;

class UpdateKbArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var KbArticle $kbArticle */
        $kbArticle = $this->route('kbArticle');

        return $this->user()?->can('update', $kbArticle) ?? false;
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
        /** @var KbArticle $kbArticle */
        $kbArticle = $this->route('kbArticle');
        $validated = $this->validated();

        return KbArticleData::fromArray([
            ...$validated,
            'status' => $kbArticle->status->value,
            'author_id' => $kbArticle->author_id,
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
