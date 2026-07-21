<?php

namespace App\Http\Requests\Admin;

use Core\Nodes\Enums\NodeCredentialField;
use Core\Nodes\Models\Node;
use Core\Providers\Services\ProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TestNodeConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Node::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'hostname' => ['required', 'string', 'max:255'],
            'module' => [
                'required',
                'string',
                'max:255',
                Rule::in(app(ProviderRegistry::class)->provisioningModuleKeys()),
            ],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'api_url' => ['nullable', 'string', 'max:2048', 'url'],
            'max_services' => ['nullable', 'integer', 'min:0'],
            'credentials' => ['nullable', 'array'],
            ...$this->credentialFieldRules(),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function credentialFieldRules(): array
    {
        $rules = [];

        foreach (NodeCredentialField::cases() as $field) {
            $rules['credentials.'.$field->value] = ['nullable', 'string', 'max:8192'];
        }

        return $rules;
    }

    /**
     * @return array{
     *     hostname: string,
     *     module: string,
     *     name?: string|null,
     *     ip_address?: string|null,
     *     api_url?: string|null,
     *     max_services?: int|null,
     *     credentials?: array<string, string>|null
     * }
     */
    public function connectionPayload(): array
    {
        $validated = $this->validated();
        $credentials = $validated['credentials'] ?? null;

        if (is_array($credentials)) {
            $credentials = array_filter(
                $credentials,
                static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
            );
        }

        return array_filter([
            'hostname' => $validated['hostname'],
            'module' => $validated['module'],
            'name' => $validated['name'] ?? null,
            'ip_address' => $validated['ip_address'] ?? null,
            'api_url' => $validated['api_url'] ?? null,
            'max_services' => isset($validated['max_services']) ? (int) $validated['max_services'] : null,
            'credentials' => is_array($credentials) && $credentials !== [] ? $credentials : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    protected function prepareForValidation(): void
    {
        $credentials = $this->input('credentials');

        $this->merge([
            'ip_address' => filled($this->input('ip_address')) ? $this->input('ip_address') : null,
            'api_url' => filled($this->input('api_url')) ? $this->input('api_url') : null,
            'max_services' => filled($this->input('max_services')) ? $this->input('max_services') : null,
            'credentials' => is_array($credentials) ? $credentials : null,
        ]);
    }
}
