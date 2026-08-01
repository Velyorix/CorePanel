<?php

namespace App\Http\Requests\Api\V1;

use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Enums\TicketStatus;
use Illuminate\Validation\Rule;

class IndexTicketRequest extends ApiIndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(TicketStatus::values())],
            'priority' => ['nullable', 'string', Rule::in(TicketPriority::values())],
            'client_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in([
                'id',
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
                ? TicketStatus::tryFrom((string) $validated['status'])
                : null,
            'priority' => isset($validated['priority'])
                ? TicketPriority::tryFrom((string) $validated['priority'])
                : null,
            'client_id' => isset($validated['client_id']) ? (int) $validated['client_id'] : null,
            'sort' => (string) ($validated['sort'] ?? 'id'),
            'dir' => $this->sortDirection('desc'),
        ];
    }
}
