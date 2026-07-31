<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSecuritySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'two_factor_required' => ['sometimes', 'boolean'],
            'password_min_length' => ['required', 'integer', 'min:8', 'max:128'],
            'password_require_special' => ['sometimes', 'boolean'],
            'password_check_compromised' => ['sometimes', 'boolean'],
            'session_timeout_minutes' => ['required', 'integer', 'min:5', 'max:10080'],
        ];
    }
}
