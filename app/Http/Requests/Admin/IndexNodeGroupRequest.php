<?php

namespace App\Http\Requests\Admin;

use Core\Nodes\Enums\NodeGroupStatus;
use Core\Nodes\Enums\NodeGroupType;
use Core\Nodes\Models\NodeGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexNodeGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', NodeGroup::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::in(NodeGroupType::values())],
            'status' => ['nullable', 'string', Rule::in(NodeGroupStatus::values())],
            'sort' => ['nullable', 'string', Rule::in(['name', 'key', 'type', 'status', 'location', 'sort_order', 'created_at'])],
            'dir' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    /**
     * @return array{
     *     q: string|null,
     *     type: NodeGroupType|null,
     *     status: NodeGroupStatus|null,
     *     sort: string,
     *     dir: string
     * }
     */
    public function filters(): array
    {
        $validated = $this->validated();
        $q = isset($validated['q']) ? trim((string) $validated['q']) : null;

        return [
            'q' => $q === '' ? null : $q,
            'type' => isset($validated['type'])
                ? NodeGroupType::tryFrom((string) $validated['type'])
                : null,
            'status' => isset($validated['status'])
                ? NodeGroupStatus::tryFrom((string) $validated['status'])
                : null,
            'sort' => (string) ($validated['sort'] ?? 'sort_order'),
            'dir' => strtolower((string) ($validated['dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
        ];
    }
}
