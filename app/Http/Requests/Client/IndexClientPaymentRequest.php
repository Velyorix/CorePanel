<?php

namespace App\Http\Requests\Client;

use Core\Billing\Enums\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.invoices.pay') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(PaymentStatus::values())],
            'sort' => ['nullable', 'string', Rule::in([
                'amount',
                'status',
                'method',
                'paid_at',
                'created_at',
            ])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     status: PaymentStatus|null,
     *     sort: string,
     *     dir: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'status' => isset($validated['status'])
                ? PaymentStatus::tryFrom((string) $validated['status'])
                : null,
            'sort' => (string) ($validated['sort'] ?? 'created_at'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
