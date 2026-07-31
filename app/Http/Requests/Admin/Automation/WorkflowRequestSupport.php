<?php

namespace App\Http\Requests\Admin\Automation;

use Core\Automation\DataTransferObjects\WorkflowData;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

final class WorkflowRequestSupport
{
    public static function prepare(FormRequest $request): void
    {
        $request->merge([
            'is_active' => $request->boolean('is_active'),
            'slug' => filled($request->input('slug')) ? $request->input('slug') : null,
            'conditions_json' => filled($request->input('conditions_json')) ? $request->input('conditions_json') : null,
            'fallback_json' => filled($request->input('fallback_json')) ? $request->input('fallback_json') : null,
        ]);
    }

    public static function validateJsonFields(FormRequest $request, Validator $validator): void
    {
        $validator->after(function (Validator $validator) use ($request): void {
            foreach (['conditions_json', 'steps_json', 'fallback_json'] as $field) {
                $raw = $request->input($field);

                if (! is_string($raw) || trim($raw) === '') {
                    continue;
                }

                json_decode($raw, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $validator->errors()->add($field, __('Must be valid JSON.'));
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $steps = self::decode($request->input('steps_json'));

            if (! is_array($steps) || ! array_is_list($steps) || $steps === []) {
                $validator->errors()->add('steps_json', __('Steps must be a non-empty JSON array.'));
            }
        });
    }

    public static function toData(FormRequest $request): WorkflowData
    {
        /** @var list<array<string, mixed>> $steps */
        $steps = self::decode($request->input('steps_json')) ?? [];
        $conditions = self::decode($request->input('conditions_json'));
        $fallback = self::decode($request->input('fallback_json'));

        return new WorkflowData(
            name: (string) $request->input('name'),
            slug: $request->input('slug'),
            triggerEvent: (string) $request->input('trigger_event'),
            conditions: is_array($conditions) ? $conditions : null,
            steps: $steps,
            fallback: is_array($fallback) ? $fallback : null,
            priority: (int) ($request->input('priority') ?? 100),
            isActive: $request->boolean('is_active'),
        );
    }

    private static function decode(mixed $raw): mixed
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        return json_decode($raw, true);
    }
}
