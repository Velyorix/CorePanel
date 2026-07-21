<?php

namespace App\Http\Requests\Admin;

use Core\Services\Models\Service;
use Core\Sync\Enums\SyncLogOutcome;
use Core\Sync\Enums\SyncLogSubject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSyncLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Service::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'subject_type' => ['nullable', 'string', Rule::in(SyncLogSubject::values())],
            'outcome' => ['nullable', 'string', Rule::in(SyncLogOutcome::values())],
            'module' => ['nullable', 'string', 'max:64'],
            'sort' => ['nullable', 'string', Rule::in([
                'id',
                'created_at',
                'subject_type',
                'outcome',
                'module',
            ])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     subject_type: SyncLogSubject|null,
     *     outcome: SyncLogOutcome|null,
     *     module: string|null,
     *     sort: string,
     *     dir: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();

        $q = isset($validated['q']) ? trim((string) $validated['q']) : null;
        $module = isset($validated['module']) ? trim((string) $validated['module']) : null;

        return [
            'q' => $q === '' ? null : $q,
            'subject_type' => isset($validated['subject_type'])
                ? SyncLogSubject::tryFrom((string) $validated['subject_type'])
                : null,
            'outcome' => isset($validated['outcome'])
                ? SyncLogOutcome::tryFrom((string) $validated['outcome'])
                : null,
            'module' => $module === '' ? null : $module,
            'sort' => (string) ($validated['sort'] ?? 'created_at'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
