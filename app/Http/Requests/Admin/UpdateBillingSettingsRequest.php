<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBillingSettingsRequest extends FormRequest
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
            'invoice_prefix' => ['required', 'string', 'max:32', 'alpha_dash:ascii'],
            'quote_prefix' => ['required', 'string', 'max:32', 'alpha_dash:ascii'],
            'credit_note_prefix' => ['required', 'string', 'max:32', 'alpha_dash:ascii'],
            'default_currency' => ['required', 'string', 'size:3', 'alpha'],
            'tax_preview_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'renewal_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
