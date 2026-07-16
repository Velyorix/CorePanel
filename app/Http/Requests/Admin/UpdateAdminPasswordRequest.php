<?php

namespace App\Http\Requests\Admin;

use Core\Auth\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAdminPasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', ...PasswordPolicy::defaults()],
        ];
    }
}
