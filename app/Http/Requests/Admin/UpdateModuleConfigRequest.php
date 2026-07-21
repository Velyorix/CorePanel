<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use JsonException;

class UpdateModuleConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'config' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $raw = trim((string) $this->input('config', ''));

            if ($raw === '') {
                $this->merge(['config_payload' => null]);

                return;
            }

            try {
                /** @var mixed $decoded */
                $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                $validator->errors()->add('config', __('Configuration must be valid JSON.'));

                return;
            }

            if (! is_array($decoded)) {
                $validator->errors()->add('config', __('Configuration must be a JSON object.'));

                return;
            }

            $this->merge(['config_payload' => $decoded]);
        });
    }

    /**
     * @return array<string, mixed>|null
     */
    public function configPayload(): ?array
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $this->input('config_payload');

        return $payload;
    }
}
