<?php

namespace App\Http\Requests\Admin\Automation;

use Core\Automation\Models\Workflow;
use Core\Automation\Support\AutomationEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Workflow::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'trigger_event' => ['nullable', 'string', Rule::in(AutomationEvent::values())],
            'is_active' => ['nullable', 'string', Rule::in(['1', '0', 'true', 'false'])],
            'sort' => ['nullable', 'string', Rule::in(['name', 'slug', 'trigger_event', 'priority', 'is_active', 'created_at'])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     trigger_event: string|null,
     *     is_active: bool|null,
     *     sort: string,
     *     dir: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $q = isset($validated['q']) ? trim((string) $validated['q']) : null;
        $trigger = isset($validated['trigger_event']) ? trim((string) $validated['trigger_event']) : null;

        $isActive = null;
        if (array_key_exists('is_active', $validated) && $validated['is_active'] !== null && $validated['is_active'] !== '') {
            $isActive = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        }

        return [
            'q' => $q === '' ? null : $q,
            'trigger_event' => $trigger === '' ? null : $trigger,
            'is_active' => $isActive,
            'sort' => (string) ($validated['sort'] ?? 'priority'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
        ];
    }
}
