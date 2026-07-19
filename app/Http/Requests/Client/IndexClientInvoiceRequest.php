<?php

namespace App\Http\Requests\Client;

use Core\Billing\Enums\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.invoices.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_values(array_filter(
            InvoiceStatus::values(),
            fn (string $status): bool => $status !== InvoiceStatus::Draft->value,
        ));

        return [
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'sort' => ['nullable', 'string', Rule::in([
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
     *     status: InvoiceStatus|null,
     *     sort: string,
     *     dir: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'status' => isset($validated['status'])
                ? InvoiceStatus::tryFrom((string) $validated['status'])
                : null,
            'sort' => (string) ($validated['sort'] ?? 'issued_at'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
