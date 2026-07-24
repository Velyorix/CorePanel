<?php

namespace App\Http\Requests\Api\V1;

use Core\Tickets\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:10000'],
            'category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'priority' => ['nullable', 'string', Rule::enum(TicketPriority::class)],
            'service_id' => ['nullable', 'integer', 'exists:services,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'invoice_id' => ['nullable', 'integer', 'exists:invoices,id'],
        ];
    }
}
