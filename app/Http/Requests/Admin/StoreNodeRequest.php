<?php

namespace App\Http\Requests\Admin;

use Core\Nodes\DataTransferObjects\NodeData;
use Core\Nodes\Enums\NodeCredentialField;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Core\Nodes\Models\Node;
use Core\Providers\Services\ProviderRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNodeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'hostname' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(NodeType::values())],
            'module' => [
                'nullable',
                'string',
                'max:255',
                Rule::in(app(ProviderRegistry::class)->provisioningModuleKeys()),
            ],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'api_url' => ['nullable', 'string', 'max:2048', 'url'],
            'status' => ['nullable', 'string', Rule::in(NodeStatus::values())],
            'max_services' => ['nullable', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'node_group_id' => ['nullable', 'integer', 'exists:node_groups,id'],
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

    public function nodeData(): NodeData
    {
        return NodeData::fromArray($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $credentials = $this->input('credentials');

        $this->merge([
            'module' => filled($this->input('module')) ? $this->input('module') : null,
            'ip_address' => filled($this->input('ip_address')) ? $this->input('ip_address') : null,
            'api_url' => filled($this->input('api_url')) ? $this->input('api_url') : null,
            'node_group_id' => filled($this->input('node_group_id')) ? $this->input('node_group_id') : null,
            'max_services' => filled($this->input('max_services')) ? $this->input('max_services') : null,
            'credentials' => is_array($credentials) ? $credentials : null,
        ]);
    }
}
