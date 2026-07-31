<?php

namespace App\Http\Requests\Client;

use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.tickets.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(TicketStatus::values())],
            'priority' => ['nullable', 'string', Rule::in(TicketPriority::values())],
            'sort' => ['nullable', 'string', Rule::in([
                'ticket_number',
                'subject',
                'status',
                'priority',
                'created_at',
                'updated_at',
            ])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     status: TicketStatus|null,
     *     priority: TicketPriority|null,
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
                ? TicketStatus::tryFrom((string) $validated['status'])
                : null,
            'priority' => isset($validated['priority'])
                ? TicketPriority::tryFrom((string) $validated['priority'])
                : null,
            'sort' => (string) ($validated['sort'] ?? 'created_at'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
