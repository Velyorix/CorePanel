<?php

namespace App\Http\Requests\Admin;

use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Models\Ticket;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Ticket $ticket */
        $ticket = $this->route('ticket');

        return $this->user()?->can('updatePriority', $ticket) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'priority' => ['required', 'string', Rule::in(TicketPriority::values())],
        ];
    }

    public function priority(): TicketPriority
    {
        return TicketPriority::from((string) $this->validated('priority'));
    }
}
