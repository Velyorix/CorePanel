<?php

namespace App\Http\Requests\Client;

use Illuminate\Foundation\Http\FormRequest;

class ApplyCheckoutCouponRequest extends FormRequest
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
            'coupon_code' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'coupon_code' => filled($this->input('coupon_code'))
                ? trim((string) $this->input('coupon_code'))
                : null,
        ]);
    }
}
