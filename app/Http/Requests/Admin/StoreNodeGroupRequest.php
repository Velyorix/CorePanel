<?php

namespace App\Http\Requests\Admin;

use Core\Nodes\DataTransferObjects\NodeGroupData;
use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Enums\NodeGroupType;
use Core\Nodes\Models\NodeGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreNodeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', NodeGroup::class) ?? false;
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
            'type' => ['required', 'string', Rule::in(NodeGroupType::values())],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', 'string', Rule::in(NodeGroupStatus::values())],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'node_ids' => ['nullable', 'array'],
            'node_ids.*' => ['integer', 'exists:nodes,id'],
        ];
    }

    public function groupData(): NodeGroupData
    {
        return NodeGroupData::fromArray($this->validated());
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
