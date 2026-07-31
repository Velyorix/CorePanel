<?php

namespace App\Http\Requests\Admin\Automation;

use Core\Automation\DataTransferObjects\WorkflowData;
use Core\Automation\Models\Workflow;
use Core\Automation\Support\AutomationEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Workflow $workflow */
        $workflow = $this->route('workflow');

        return $this->user()?->can('update', $workflow) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'trigger_event' => ['required', 'string', Rule::in(AutomationEvent::values())],
            'conditions_json' => ['nullable', 'string'],
            'steps_json' => ['required', 'string'],
            'fallback_json' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        WorkflowRequestSupport::validateJsonFields($this, $validator);
    }

    public function workflowData(): WorkflowData
    {
        return WorkflowRequestSupport::toData($this);
    }

    protected function prepareForValidation(): void
    {
        WorkflowRequestSupport::prepare($this);
    }
}
