<?php

namespace App\Http\Requests\Client;

use Core\Orders\DataTransferObjects\CheckoutDraftData;
use Core\Orders\Services\CheckoutDraftService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitCheckoutRequest extends FormRequest
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
        $enabledMethods = app(CheckoutDraftService::class)->enabledPaymentMethodKeys();

        return [
            'company_name' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:64'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2'],
            'postal_code' => ['required', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:64'],
            'contact_name' => ['required', 'string', 'max:255'],
            'contact_email' => ['required', 'email', 'max:255'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'payment_method' => ['required', 'string', Rule::in($enabledMethods)],
        ];
    }

    public function draftData(): CheckoutDraftData
    {
        return CheckoutDraftData::fromArray($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => filled($this->input('country'))
                ? strtoupper((string) $this->input('country'))
                : null,
            'coupon_code' => filled($this->input('coupon_code'))
                ? trim((string) $this->input('coupon_code'))
                : null,
        ]);
    }
}
