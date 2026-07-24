<?php

namespace App\Http\Requests\Api\V1;

use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Illuminate\Validation\Rule;

class IndexNodeRequest extends ApiIndexRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->paginationRules(),
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(NodeType::values())],
            'status' => ['nullable', 'string', Rule::in(NodeStatus::values())],
            'module' => ['nullable', 'string', 'max:255'],
            'node_group_id' => ['nullable', 'integer', 'min:1'],
            'sort' => ['nullable', 'string', Rule::in([
                'id',
                'name',
                'hostname',
                'type',
                'status',
                'module',
                'sort_order',
                'created_at',
            ])],
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

        return [
            'q' => $this->nullableTrimmedString(isset($validated['q']) ? (string) $validated['q'] : null),
            'type' => isset($validated['type'])
                ? NodeType::tryFrom((string) $validated['type'])
                : null,
            'status' => isset($validated['status'])
                ? NodeStatus::tryFrom((string) $validated['status'])
                : null,
            'module' => $this->nullableTrimmedString(isset($validated['module']) ? (string) $validated['module'] : null),
            'node_group_id' => isset($validated['node_group_id']) ? (int) $validated['node_group_id'] : null,
            'sort' => (string) ($validated['sort'] ?? 'sort_order'),
            'dir' => $this->sortDirection('asc'),
        ];
    }
}
