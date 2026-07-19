<?php

namespace App\Http\Requests\Client;

use Core\Billing\Enums\QuoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.quotes.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_values(array_filter(
            QuoteStatus::values(),
            fn (string $status): bool => $status !== QuoteStatus::Draft->value,
        ));

        return [
            'status' => ['nullable', 'string', Rule::in($statuses)],
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
     *     status: QuoteStatus|null,
     *     sort: string,
     *     dir: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'status' => isset($validated['status'])
                ? QuoteStatus::tryFrom((string) $validated['status'])
                : null,
            'sort' => (string) ($validated['sort'] ?? 'sent_at'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
