<?php

namespace App\Http\Requests\Api\V1;

use Core\Clients\Enums\ClientStatus;
use Illuminate\Validation\Rule;

class IndexClientRequest extends ApiIndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ClientStatus::values())],
            'sort' => ['nullable', 'string', Rule::in(['id', 'company_name', 'country', 'status', 'created_at'])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{q: string|null, status: ClientStatus|null, sort: string, dir: string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        return [
            'q' => $this->nullableTrimmedString(isset($validated['q']) ? (string) $validated['q'] : null),
            'status' => isset($validated['status'])
                ? ClientStatus::tryFrom((string) $validated['status'])
                : null,
            'sort' => (string) ($validated['sort'] ?? 'id'),
            'dir' => $this->sortDirection('asc'),
        ];
    }
}
