<?php

namespace App\Http\Requests\Client;

use Core\Services\Enums\ServiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.services.view') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ServiceStatus::values())],
            'sort' => ['nullable', 'string', Rule::in([
                'hostname',
                'status',
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
                ? ServiceStatus::tryFrom((string) $validated['status'])
                : null,
            'sort' => (string) ($validated['sort'] ?? 'created_at'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
