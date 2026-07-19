<?php

namespace App\Http\Requests\Admin;

use Core\Billing\Enums\QuoteStatus;
use Core\Billing\Models\Quote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Quote::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(QuoteStatus::values())],
            'sort' => ['nullable', 'string', Rule::in([
                'quote_number',
                'status',
                'total_amount',
                'valid_until',
                'sent_at',
                'created_at',
            ])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     status: QuoteStatus|null,
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
                ? QuoteStatus::tryFrom((string) $validated['status'])
                : null,
            'sort' => (string) ($validated['sort'] ?? 'created_at'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
