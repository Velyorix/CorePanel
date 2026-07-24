<?php

namespace App\Http\Requests\Api\V1;

use Core\Services\Enums\ServiceStatus;
use Illuminate\Validation\Rule;

class IndexServiceRequest extends ApiIndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ServiceStatus::values())],
            'client_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in([
                'id',
                'status',
                'hostname',
                'module',
                'next_billing_date',
                'created_at',
            ])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     status: ServiceStatus|null,
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
                ? ServiceStatus::tryFrom((string) $validated['status'])
                : null,
            'client_id' => isset($validated['client_id']) ? (int) $validated['client_id'] : null,
            'sort' => (string) ($validated['sort'] ?? 'id'),
            'dir' => $this->sortDirection('desc'),
        ];
    }
}
