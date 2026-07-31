<?php

namespace App\Http\Requests\Admin\Automation;

use Core\Automation\DataTransferObjects\AutomationRuleData;
use Core\Automation\Models\AutomationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateAutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AutomationRule $rule */
        $rule = $this->route('automationRule');

        return $this->user()?->can('update', $rule) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'condition_json' => ['required', 'string'],
            'action_json' => ['required', 'string'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        AutomationRuleRequestSupport::validateJsonFields($this, $validator);
    }

    public function ruleData(): AutomationRuleData
    {
        return AutomationRuleRequestSupport::toData($this);
    }

    protected function prepareForValidation(): void
    {
        AutomationRuleRequestSupport::prepare($this);
    }
}
