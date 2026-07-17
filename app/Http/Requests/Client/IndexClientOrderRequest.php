<?php

namespace App\Http\Requests\Client;

use Core\Orders\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.orders.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_values(array_filter(
            OrderStatus::values(),
            fn (string $status): bool => $status !== OrderStatus::Draft->value,
        ));

        return [
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'sort' => ['nullable', 'string', Rule::in([
                'order_number',
                'status',
                'total_amount',
                'placed_at',
                'created_at',
            ])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     status: OrderStatus|null,
     *     sort: string,
     *     dir: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'status' => isset($validated['status'])
                ? OrderStatus::tryFrom((string) $validated['status'])
                : null,
            'sort' => (string) ($validated['sort'] ?? 'placed_at'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
