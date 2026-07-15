<?php

namespace Core\Admin\Navigation;

use Core\Auth\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class AdminNavigation
{
    /**
     * Menu tree for the authenticated user (filtered by permissions).
     *
     * @return list<array{label: string|null, items: list<array<string, mixed>>}>
     */
    public function forUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return collect($this->definition())
            ->map(function (array $section) use ($user): ?array {
                $items = collect($section['items'] ?? [])
                    ->map(fn (array $item): ?array => $this->resolveItem($item, $user))
                    ->filter()
                    ->values()
                    ->all();

                if ($items === []) {
                    return null;
                }

                return [
                    'label' => $section['label'] ?? null,
                    'items' => $items,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<array{label: string|null, items: list<array<string, mixed>>}>
     */
    public function definition(): array
    {
        return [
            [
                'label' => null,
                'items' => [
                    [
                        'label' => __('Dashboard'),
                        'route' => null,
                        'permission' => 'admin.access',
                    ],
                    [
                        'label' => __('Clients'),
                        'route' => null,
                        'permission' => 'clients.view',
                    ],
                    [
                        'label' => __('Users'),
                        'route' => null,
                        'permission' => 'users.view',
                        'children' => [
                            [
                                'label' => __('Roles'),
                                'route' => 'admin.roles.index',
                                'routeIs' => ['admin.roles.*'],
                                'permission' => 'roles.view',
                            ],
                            [
                                'label' => __('Permissions'),
                                'route' => 'admin.permissions.index',
                                'routeIs' => ['admin.permissions.*'],
                                'permission' => 'roles.view',
                            ],
                        ],
                    ],
                    [
                        'label' => __('Products'),
                        'route' => null,
                        'permission' => null,
                    ],
                    [
                        'label' => __('Services'),
                        'route' => null,
                        'permission' => 'services.view',
                    ],
                ],
            ],
            [
                'label' => __('Billing'),
                'items' => [
                    [
                        'label' => __('Invoices'),
                        'route' => null,
                        'permission' => 'billing.invoices.view',
                    ],
                    [
                        'label' => __('Payments'),
                        'route' => null,
                        'permission' => 'billing.payments.view',
                    ],
                    [
                        'label' => __('Credits'),
                        'route' => null,
                        'permission' => 'billing.invoices.view',
                    ],
                ],
            ],
            [
                'label' => __('Support'),
                'items' => [
                    [
                        'label' => __('Tickets'),
                        'route' => null,
                        'permission' => 'tickets.view',
                    ],
                    [
                        'label' => __('Departments'),
                        'route' => null,
                        'permission' => 'tickets.view',
                    ],
                    [
                        'label' => __('SLA'),
                        'route' => null,
                        'permission' => 'tickets.view',
                    ],
                ],
            ],
            [
                'label' => __('Infrastructure'),
                'items' => [
                    [
                        'label' => __('Nodes'),
                        'route' => null,
                        'permission' => 'nodes.view',
                    ],
                    [
                        'label' => __('Groups'),
                        'route' => null,
                        'permission' => 'nodes.view',
                    ],
                    [
                        'label' => __('Metrics'),
                        'route' => null,
                        'permission' => 'nodes.view',
                    ],
                ],
            ],
            [
                'label' => null,
                'items' => [
                    [
                        'label' => __('Orders'),
                        'route' => null,
                        'permission' => null,
                    ],
                    [
                        'label' => __('Modules'),
                        'route' => null,
                        'permission' => null,
                    ],
                    [
                        'label' => __('Themes'),
                        'route' => null,
                        'permission' => null,
                    ],
                ],
            ],
            [
                'label' => __('Settings'),
                'items' => [
                    [
                        'label' => __('General'),
                        'route' => null,
                        'permission' => 'settings.view',
                    ],
                    [
                        'label' => __('Security'),
                        'route' => null,
                        'permission' => 'settings.view',
                    ],
                    [
                        'label' => __('Payments'),
                        'route' => null,
                        'permission' => 'settings.view',
                    ],
                    [
                        'label' => __('Mail'),
                        'route' => null,
                        'permission' => 'settings.view',
                    ],
                    [
                        'label' => __('API'),
                        'route' => null,
                        'permission' => 'settings.view',
                    ],
                ],
            ],
            [
                'label' => __('Logs'),
                'items' => [
                    [
                        'label' => __('Activity Logs'),
                        'route' => null,
                        'permission' => 'audit.view',
                    ],
                    [
                        'label' => __('Audit Logs'),
                        'route' => null,
                        'permission' => 'audit.view',
                    ],
                ],
            ],
            [
                'label' => null,
                'items' => [
                    [
                        'label' => __('System Health'),
                        'route' => null,
                        'permission' => null,
                    ],
                    [
                        'label' => __('Marketplace'),
                        'route' => null,
                        'permission' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private function resolveItem(array $item, User $user): ?array
    {
        if (! $this->userCan($user, $item['permission'] ?? null)) {
            return null;
        }

        $children = collect($item['children'] ?? [])
            ->map(fn (array $child): ?array => $this->resolveItem($child, $user))
            ->filter()
            ->values()
            ->all();

        $routeName = $item['route'] ?? null;
        $url = filled($routeName) && Route::has($routeName)
            ? route($routeName)
            : null;

        $routeIs = $item['routeIs'] ?? (filled($routeName) ? [$routeName] : []);

        return [
            'label' => $item['label'],
            'url' => $url,
            'active' => $this->isActive($routeIs),
            'placeholder' => $url === null,
            'children' => $children,
        ];
    }

    /**
     * @param  list<string>|string  $routeIs
     */
    private function isActive(array|string $routeIs): bool
    {
        $patterns = is_array($routeIs) ? $routeIs : [$routeIs];

        if ($patterns === []) {
            return false;
        }

        return request()->routeIs(...$patterns);
    }

    private function userCan(User $user, ?string $permission): bool
    {
        if ($permission === null) {
            return true;
        }

        return Gate::forUser($user)->allows($permission);
    }
}
