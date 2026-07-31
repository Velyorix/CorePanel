<?php

namespace App\Http\Requests\Admin\Automation;

use Core\Automation\DataTransferObjects\AutomationRuleData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class AutomationRuleRequestSupport
{
    public static function prepare(FormRequest $request): void
    {
        $request->merge([
            'is_active' => $request->boolean('is_active'),
        ]);
    }

    public static function validateJsonFields(FormRequest $request, Validator $validator): void
    {
        $validator->after(function (Validator $validator) use ($request): void {
            foreach (['condition_json', 'action_json'] as $field) {
                $raw = $request->input($field);

                if (! is_string($raw) || trim($raw) === '') {
                    $validator->errors()->add($field, __('Must be valid JSON.'));

                    continue;
                }

                $decoded = json_decode($raw, true);

                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                    $validator->errors()->add($field, __('Must be a valid JSON object.'));
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $action = json_decode((string) $request->input('action_json'), true);

            if (! is_array($action) || ! isset($action['type']) || ! is_string($action['type']) || trim($action['type']) === '') {
                $validator->errors()->add('action_json', __('Action JSON must include a type.'));
            }
        });
    }

    public static function toData(FormRequest $request): AutomationRuleData
    {
        /** @var array<string, mixed> $conditions */
        $conditions = json_decode((string) $request->input('condition_json'), true) ?? [];
        /** @var array<string, mixed> $action */
        $action = json_decode((string) $request->input('action_json'), true) ?? [];

        return new AutomationRuleData(
            name: (string) $request->input('name'),
            conditionJson: $conditions,
            actionJson: $action,
            priority: (int) ($request->input('priority') ?? 100),
            isActive: $request->boolean('is_active'),
        );
    }
}
