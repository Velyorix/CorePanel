<?php

namespace Core\Automation\Services;

use Core\Automation\DataTransferObjects\AutomationRuleData;
use Core\Automation\Models\AutomationRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class AutomationRuleAdminService
{
    /**
     * @param  array{
     *     q?: string|null,
     *     is_active?: bool|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $isActive = $filters['is_active'] ?? null;
        $sort = $filters['sort'] ?? 'priority';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['name', 'priority', 'is_active', 'created_at'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'priority';
        }

        return AutomationRule::query()
            ->withCount('logs')
            ->when(is_bool($isActive), fn ($q) => $q->where('is_active', $isActive))
            ->when($search !== null && $search !== '', function ($q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%');
            })
            ->orderBy($sort, $dir)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(AutomationRuleData $data): AutomationRule
    {
        $this->assertAction($data->actionJson);

        return AutomationRule::query()->create([
            'name' => $data->name,
            'condition_json' => $data->conditionJson,
            'action_json' => $data->actionJson,
            'priority' => $data->priority,
            'is_active' => $data->isActive,
        ]);
    }

    public function update(AutomationRule $rule, AutomationRuleData $data): AutomationRule
    {
        $this->assertAction($data->actionJson);

        $rule->forceFill([
            'name' => $data->name,
            'condition_json' => $data->conditionJson,
            'action_json' => $data->actionJson,
            'priority' => $data->priority,
            'is_active' => $data->isActive,
        ])->save();

        return $rule->fresh() ?? $rule;
    }

    public function delete(AutomationRule $rule): void
    {
        $rule->delete();
    }

    /**
     * @param  array<string, mixed>  $action
     */
    private function assertAction(array $action): void
    {
        if (! isset($action['type']) || ! is_string($action['type']) || trim($action['type']) === '') {
            throw new InvalidArgumentException('Rule action_json must include a type.');
        }
    }
}
