<?php

namespace App\Http\Requests\Auth;

use Core\Auth\DataTransferObjects\RegisterData;
use Core\Auth\Rules\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<\Illuminate\Contracts\Validation\ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'confirmed', ...PasswordPolicy::defaults()],
        ];
    }

    public function registerData(): RegisterData
    {
        return RegisterData::fromArray($this->validated());
    }
}
