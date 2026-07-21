<?php

namespace App\Http\Requests\Admin;

use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Core\Nodes\Models\Node;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Node::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(NodeType::values())],
            'status' => ['nullable', 'string', Rule::in(NodeStatus::values())],
            'module' => ['nullable', 'string', 'max:255'],
            'node_group_id' => ['nullable', 'integer', 'exists:node_groups,id'],
            'sort' => ['nullable', 'string', Rule::in(['name', 'hostname', 'type', 'status', 'module', 'sort_order', 'created_at'])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     type: NodeType|null,
     *     status: NodeStatus|null,
     *     module: string|null,
     *     node_group_id: int|null,
     *     sort: string,
     *     dir: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $q = isset($validated['q']) ? trim((string) $validated['q']) : null;
        $module = isset($validated['module']) ? trim((string) $validated['module']) : null;

        return [
            'q' => $q === '' ? null : $q,
            'type' => isset($validated['type'])
                ? NodeType::tryFrom((string) $validated['type'])
                : null,
            'status' => isset($validated['status'])
                ? NodeStatus::tryFrom((string) $validated['status'])
                : null,
            'module' => $module === '' ? null : $module,
            'node_group_id' => isset($validated['node_group_id'])
                ? (int) $validated['node_group_id']
                : null,
            'sort' => (string) ($validated['sort'] ?? 'sort_order'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
        ];
    }
}
