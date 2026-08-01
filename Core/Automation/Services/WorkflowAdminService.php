<?php

namespace Core\Automation\Services;

use Core\Automation\DataTransferObjects\WorkflowData;
use Core\Automation\Models\Workflow;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WorkflowAdminService
{
    /**
     * @param  array{
     *     q?: string|null,
     *     trigger_event?: string|null,
     *     is_active?: bool|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $trigger = isset($filters['trigger_event']) ? trim((string) $filters['trigger_event']) : null;
        $isActive = $filters['is_active'] ?? null;
        $sort = $filters['sort'] ?? 'priority';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $allowedSorts = ['name', 'slug', 'trigger_event', 'priority', 'is_active', 'created_at'];

        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'priority';
        }

        return Workflow::query()
            ->withCount('logs')
            ->when($trigger !== null && $trigger !== '', fn ($q) => $q->where('trigger_event', $trigger))
            ->when(is_bool($isActive), fn ($q) => $q->where('is_active', $isActive))
            ->when($search !== null && $search !== '', function ($q) use ($search): void {
                $q->where(function ($nested) use ($search): void {
                    $nested->where('name', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%')
                        ->orWhere('trigger_event', 'like', '%'.$search.'%');
                });
            })
            ->orderBy($sort, $dir)
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(WorkflowData $data): Workflow
    {
        $this->assertSteps($data->steps);

        return Workflow::query()->create([
            'name' => $data->name,
            'slug' => $this->resolveSlug($data->slug, $data->name),
            'trigger_event' => $data->triggerEvent,
            'conditions' => $data->conditions,
            'steps' => $data->steps,
            'fallback' => $data->fallback,
            'priority' => $data->priority,
            'is_active' => $data->isActive,
        ]);
    }

    public function update(Workflow $workflow, WorkflowData $data): Workflow
    {
        $this->assertSteps($data->steps);

        $workflow->forceFill([
            'name' => $data->name,
            'slug' => $this->resolveSlug($data->slug, $data->name, $workflow),
            'trigger_event' => $data->triggerEvent,
            'conditions' => $data->conditions,
            'steps' => $data->steps,
            'fallback' => $data->fallback,
            'priority' => $data->priority,
            'is_active' => $data->isActive,
        ])->save();

        return $workflow->fresh() ?? $workflow;
    }

    public function delete(Workflow $workflow): void
    {
        $workflow->delete();
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    private function assertSteps(array $steps): void
    {
        if ($steps === []) {
            throw new InvalidArgumentException('Workflow steps cannot be empty.');
        }

        foreach ($steps as $index => $step) {
            if (! is_array($step) || ! isset($step['type']) || ! is_string($step['type']) || trim($step['type']) === '') {
                throw new InvalidArgumentException("Workflow step #{$index} must include a type.");
            }
        }
    }

    private function resolveSlug(?string $slug, string $name, ?Workflow $ignore = null): string
    {
        $base = filled($slug) ? Str::slug($slug) : Str::slug($name);
        $base = $base !== '' ? $base : Str::lower(Str::random(8));
        $candidate = $base;
        $suffix = 2;

        while (
            Workflow::query()
                ->where('slug', $candidate)
                ->when($ignore !== null, fn ($q) => $q->whereKeyNot($ignore->id))
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
