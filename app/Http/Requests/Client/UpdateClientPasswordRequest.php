<?php

namespace App\Http\Requests\Client;

use Core\Auth\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClientPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('client.account.manage') ?? false;
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

