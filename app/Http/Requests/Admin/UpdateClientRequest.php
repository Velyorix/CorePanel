<?php

namespace App\Http\Requests\Admin;

use Core\Clients\DataTransferObjects\ClientData;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Client $client */
        $client = $this->route('client');

        return $this->user()?->can('update', $client) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:64'],
            'status' => ['required', 'string', Rule::in(ClientStatus::values())],
        ];
    }

    public function clientData(): ClientData
    {
        return ClientData::fromArray($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => filled($this->input('user_id')) ? $this->input('user_id') : null,
            'country' => filled($this->input('country'))
                ? strtoupper((string) $this->input('country'))
                : null,
        ]);
    }
}
