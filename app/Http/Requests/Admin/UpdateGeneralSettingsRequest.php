<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGeneralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string|\Illuminate\Validation\Rules\In>>
     */
    public function rules(): array
    {
        $locales = array_values(array_filter((array) config('corepanel.locale.supported', ['en'])));

        return [
            'site_name' => ['required', 'string', 'max:120'],
            'locale' => ['required', 'string', 'max:16', Rule::in($locales)],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }
}
