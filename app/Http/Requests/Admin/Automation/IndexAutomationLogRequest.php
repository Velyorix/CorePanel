<?php

namespace App\Http\Requests\Admin\Automation;

use Core\Automation\Enums\AutomationLogStatus;
use Core\Automation\Models\AutomationLog;
use Core\Automation\Support\AutomationEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAutomationLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AutomationLog::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(AutomationLogStatus::values())],
            'trigger_event' => ['nullable', 'string', Rule::in(AutomationEvent::values())],
            'workflow_id' => ['nullable', 'integer', 'min:1'],
            'automation_rule_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in(['id', 'created_at', 'status', 'trigger_event', 'attempt', 'finished_at'])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     status: AutomationLogStatus|null,
     *     trigger_event: string|null,
     *     workflow_id: int|null,
     *     automation_rule_id: int|null,
     *     sort: string,
     *     dir: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $q = isset($validated['q']) ? trim((string) $validated['q']) : null;
        $trigger = isset($validated['trigger_event']) ? trim((string) $validated['trigger_event']) : null;

        return [
            'q' => $q === '' ? null : $q,
            'status' => isset($validated['status'])
                ? AutomationLogStatus::tryFrom((string) $validated['status'])
                : null,
            'trigger_event' => $trigger === '' ? null : $trigger,
            'workflow_id' => isset($validated['workflow_id']) ? (int) $validated['workflow_id'] : null,
            'automation_rule_id' => isset($validated['automation_rule_id']) ? (int) $validated['automation_rule_id'] : null,
            'sort' => (string) ($validated['sort'] ?? 'created_at'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
    }
}
