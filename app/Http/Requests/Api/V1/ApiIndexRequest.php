<?php

namespace App\Http\Requests\Api\V1;

use Core\API\Support\ApiPagination;
use Illuminate\Foundation\Http\FormRequest;

abstract class ApiIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function perPage(): int
    {
        return ApiPagination::perPage($this);
    }

    /**
     * @return array<string, mixed>
     */
    protected function paginationRules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.ApiPagination::maxPerPage()],
        ];
    }

    protected function nullableTrimmedString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    protected function sortDirection(string $default = 'desc'): string
    {
        $dir = strtolower((string) ($this->validated('dir') ?? $default));

        if ($default === 'asc') {
            return $dir === 'desc' ? 'desc' : 'asc';
        }

        return $dir === 'asc' ? 'asc' : 'desc';
    }
}
