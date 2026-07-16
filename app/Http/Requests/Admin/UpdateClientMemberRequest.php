<?php

namespace App\Http\Requests\Admin;

use Core\Clients\DataTransferObjects\ClientMembershipData;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Core\Clients\Models\ClientUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientMemberRequest extends FormRequest
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
            'role' => ['required', 'string', Rule::in(ClientMembershipRole::values())],
        ];
    }

    public function membershipData(ClientUser $membership): ClientMembershipData
    {
        return ClientMembershipData::fromArray([
            'user_id' => $membership->user_id,
            ...$this->validated(),
        ]);
    }
}
