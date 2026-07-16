<?php

namespace App\Http\Requests\Install;

use Illuminate\Foundation\Http\FormRequest;

class ActivateLicenseRequest extends FormRequest
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
            'license_key' => ['required', 'string', 'max:255'],
        ];
    }
}
