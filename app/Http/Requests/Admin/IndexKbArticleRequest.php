<?php

namespace App\Http\Requests\Admin;

use Core\KnowledgeBase\Enums\KbArticleStatus;
use Core\KnowledgeBase\Models\KbArticle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexKbArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', KbArticle::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(KbArticleStatus::values())],
            'category_id' => ['nullable', 'integer', 'exists:kb_categories,id'],
            'sort' => ['nullable', 'string', Rule::in([
                'title',
                'slug',
                'status',
                'published_at',
                'sort_order',
                'created_at',
                'updated_at',
            ])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     status: KbArticleStatus|null,
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
                ? KbArticleStatus::tryFrom((string) $validated['status'])
                : null,
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'sort' => (string) ($validated['sort'] ?? 'updated_at'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
