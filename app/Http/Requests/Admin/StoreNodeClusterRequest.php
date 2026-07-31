<?php

namespace App\Http\Requests\Admin;

use Core\Nodes\DataTransferObjects\NodeClusterData;
use Core\Nodes\Enums\NodeClusterStatus;
use Core\Nodes\Models\NodeCluster;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNodeClusterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', NodeCluster::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'key' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(NodeClusterStatus::values())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'node_ids' => ['nullable', 'array'],
            'node_ids.*' => ['integer', 'exists:nodes,id'],
        ];
    }

    public function clusterData(): NodeClusterData
    {
        return NodeClusterData::fromArray($this->validated());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'key' => filled($this->input('key')) ? $this->input('key') : null,
            'location' => filled($this->input('location')) ? $this->input('location') : null,
            'node_ids' => $this->input('node_ids', []),
        ]);
    }
}
