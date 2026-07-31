<?php

namespace App\Http\Requests\Api\V1;

use Core\Billing\Enums\InvoiceStatus;
use Illuminate\Validation\Rule;

class IndexInvoiceRequest extends ApiIndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_values(array_filter(
            InvoiceStatus::values(),
            static fn (string $value): bool => $value !== InvoiceStatus::Draft->value,
        ));

        return [
            ...$this->paginationRules(),
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'client_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in([
                'id',
                'invoice_number',
                'status',
                'total_amount',
                'issued_at',
                'due_at',
                'created_at',
            ])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     status: InvoiceStatus|null,
     *     client_id: int|null,
     *     sort: string,
     *     dir: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => $this->nullableTrimmedString(isset($validated['q']) ? (string) $validated['q'] : null),
            'status' => isset($validated['status'])
                ? InvoiceStatus::tryFrom((string) $validated['status'])
                : null,
            'client_id' => isset($validated['client_id']) ? (int) $validated['client_id'] : null,
            'sort' => (string) ($validated['sort'] ?? 'id'),
            'dir' => $this->sortDirection('desc'),
        ];
    }
}
