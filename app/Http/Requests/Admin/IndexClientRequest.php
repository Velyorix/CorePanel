<?php

namespace App\Http\Requests\Admin;

use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Client::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(ClientStatus::values())],
            'sort' => ['nullable', 'string', Rule::in(['company_name', 'country', 'status', 'created_at'])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{q: string|null, status: ClientStatus|null, sort: string, dir: string}
     */
    public function filters(): array
    {
        $validated = $this->validated();

        $status = isset($validated['status'])
            ? ClientStatus::tryFrom((string) $validated['status'])
            : null;

        $sort = (string) ($validated['sort'] ?? 'created_at');
        $dir = strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        $q = isset($validated['q']) ? trim((string) $validated['q']) : null;

        return [
            'q' => $q === '' ? null : $q,
            'status' => $status,
            'sort' => $sort,
            'dir' => $dir,
        ];
    }
}
