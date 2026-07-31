<?php

namespace App\Http\Requests\Admin;

use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClientInvitationRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(ClientMembershipRole::values())],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'max:255'],
        ];
    }

    /**
     * @return array{email: string, role: ClientMembershipRole, permissions: array<string>|null}
     */
    public function invitationData(): array
    {
        $validated = $this->validated();

        $permissions = $validated['permissions'] ?? null;
        if (is_array($permissions)) {
            $permissions = array_values(array_filter($permissions, static fn ($value): bool => filled($value)));
        }

        return [
            'email' => (string) $validated['email'],
            'role' => ClientMembershipRole::from((string) $validated['role']),
            'permissions' => $permissions,
        ];
    }
}

