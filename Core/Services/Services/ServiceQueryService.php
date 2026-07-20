<?php

namespace Core\Services\Services;

use Core\Clients\Models\Client;
use Core\Services\Enums\ServiceStatus;
use Core\Services\Models\Service;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ServiceQueryService
{
    /**
     * @param  array{
     *     q?: string|null,
     *     status?: ServiceStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForAdmin(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, [
            'id',
            'status',
            'hostname',
            'module',
            'next_billing_date',
            'created_at',
        ], true)) {
            $sort = 'created_at';
        }

        $query = Service::query()->with(['client.owner', 'product']);

        if ($status instanceof ServiceStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($search, $term): void {
                $builder
                    ->where('hostname', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhere('external_id', 'like', $term)
                    ->orWhere('module', 'like', $term)
                    ->orWhereHas('client', function ($clientQuery) use ($term): void {
                        $clientQuery
                            ->where('company_name', 'like', $term)
                            ->orWhereHas('owner', function ($ownerQuery) use ($term): void {
                                $ownerQuery
                                    ->where('name', 'like', $term)
                                    ->orWhere('email', 'like', $term);
                            });
                    })
                    ->orWhereHas('product', function ($productQuery) use ($term): void {
                        $productQuery
                            ->where('name', 'like', $term)
                            ->orWhere('slug', 'like', $term);
                    });

                if (ctype_digit((string) $search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $query->orderBy($sort, $dir)->orderByDesc('id');

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     status?: ServiceStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public function paginateForClient(Client $client, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'created_at';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, [
            'status',
            'hostname',
            'module',
            'next_billing_date',
            'created_at',
        ], true)) {
            $sort = 'created_at';
        }

        $query = Service::query()
            ->with(['product'])
            ->where('client_id', $client->id);

        if ($status instanceof ServiceStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function ($builder) use ($search, $term): void {
                $builder
                    ->where('hostname', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhere('module', 'like', $term)
                    ->orWhereHas('product', function ($productQuery) use ($term): void {
                        $productQuery
                            ->where('name', 'like', $term)
                            ->orWhere('slug', 'like', $term);
                    });

                if (ctype_digit((string) $search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $query->orderBy($sort, $dir)->orderByDesc('id');

        return $query->paginate($perPage)->withQueryString();
    }
}
