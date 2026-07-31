<?php

namespace App\Http\Requests\Admin;

use Core\Clients\DataTransferObjects\ClientMembershipData;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientMemberRequest extends FormRequest
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
        /** @var Client $client */
        $client = $this->route('client');

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('client_users', 'user_id')->where(
                    fn ($query) => $query->where('client_id', $client->id),
                ),
            ],
            'role' => ['required', 'string', Rule::in(ClientMembershipRole::values())],
        ];
    }

    public function membershipData(): ClientMembershipData
    {
        return ClientMembershipData::fromArray($this->validated());
    }
}
