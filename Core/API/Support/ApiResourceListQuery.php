<?php

namespace Core\API\Support;

use Core\Billing\Enums\InvoiceStatus;
use Core\Clients\Enums\ClientStatus;
use Core\Nodes\Enums\NodeStatus;
use Core\Nodes\Enums\NodeType;
use Core\Services\Enums\ServiceStatus;
use Core\Tickets\Enums\TicketPriority;
use Core\Tickets\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;

/**
 * Apply standard list filters/sort to API resource queries.
 */
final class ApiResourceListQuery
{
    /**
     * @param  array{
     *     q?: string|null,
     *     status?: ClientStatus|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public static function applyClients(Builder $query, array $filters): Builder
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $sort = $filters['sort'] ?? 'id';
        $dir = ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        if ($status instanceof ClientStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function (Builder $builder) use ($search, $term): void {
                $builder
                    ->where('company_name', 'like', $term)
                    ->orWhere('country', 'like', $term)
                    ->orWhere('vat_number', 'like', $term)
                    ->orWhere('city', 'like', $term);

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        return $query->orderBy($sort, $dir)->orderBy('id');
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     status?: ServiceStatus|null,
     *     client_id?: int|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public static function applyServices(Builder $query, array $filters): Builder
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $clientId = $filters['client_id'] ?? null;
        $sort = $filters['sort'] ?? 'id';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (is_int($clientId) && $clientId > 0) {
            $query->where('client_id', $clientId);
        }

        if ($status instanceof ServiceStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function (Builder $builder) use ($search, $term): void {
                $builder
                    ->where('hostname', 'like', $term)
                    ->orWhere('ip_address', 'like', $term)
                    ->orWhere('module', 'like', $term)
                    ->orWhereHas('product', function (Builder $productQuery) use ($term): void {
                        $productQuery
                            ->where('name', 'like', $term)
                            ->orWhere('slug', 'like', $term);
                    });

                if (ctype_digit((string) $search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        return $query->orderBy($sort, $dir)->orderByDesc('id');
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     status?: InvoiceStatus|null,
     *     client_id?: int|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public static function applyInvoices(Builder $query, array $filters): Builder
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $clientId = $filters['client_id'] ?? null;
        $sort = $filters['sort'] ?? 'id';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (is_int($clientId) && $clientId > 0) {
            $query->where('client_id', $clientId);
        }

        if ($status instanceof InvoiceStatus) {
            $query->where('status', $status->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->where('invoice_number', 'like', $term)
                    ->orWhere('contact_name', 'like', $term)
                    ->orWhere('contact_email', 'like', $term)
                    ->orWhere('company_name', 'like', $term);
            });
        }

        return $query->orderBy($sort, $dir)->orderByDesc('id');
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     status?: TicketStatus|null,
     *     priority?: TicketPriority|null,
     *     client_id?: int|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
     */
    public static function applyTickets(Builder $query, array $filters): Builder
    {
        $search = $filters['q'] ?? null;
        $status = $filters['status'] ?? null;
        $priority = $filters['priority'] ?? null;
        $clientId = $filters['client_id'] ?? null;
        $sort = $filters['sort'] ?? 'id';
        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (is_int($clientId) && $clientId > 0) {
            $query->where('client_id', $clientId);
        }

        if ($status instanceof TicketStatus) {
            $query->where('status', $status->value);
        }

        if ($priority instanceof TicketPriority) {
            $query->where('priority', $priority->value);
        }

        if (filled($search)) {
            $term = '%'.$search.'%';

            $query->where(function (Builder $builder) use ($search, $term): void {
                $builder
                    ->where('ticket_number', 'like', $term)
                    ->orWhere('subject', 'like', $term);

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        return $query->orderBy($sort, $dir)->orderByDesc('id');
    }

    /**
     * @param  array{
     *     q?: string|null,
     *     type?: NodeType|null,
     *     status?: NodeStatus|null,
     *     module?: string|null,
     *     node_group_id?: int|null,
     *     sort?: string,
     *     dir?: string
     * }  $filters
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
    public static function nodeServiceFilters(array $filters): array
    {
        return [
            'q' => $filters['q'] ?? null,
            'type' => $filters['type'] ?? null,
            'status' => $filters['status'] ?? null,
            'module' => $filters['module'] ?? null,
            'node_group_id' => $filters['node_group_id'] ?? null,
            'sort' => $filters['sort'] ?? 'sort_order',
            'dir' => ($filters['dir'] ?? 'asc') === 'desc' ? 'desc' : 'asc',
        ];
    }
}
