<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class IndexClientKnowledgeBaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.kb.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:kb_categories,id'],
            'sort' => ['nullable', 'string', 'in:title,published_at,sort_order,created_at'],
            'dir' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
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
            'category_id' => isset($validated['category_id']) ? (int) $validated['category_id'] : null,
            'sort' => (string) ($validated['sort'] ?? 'published_at'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
