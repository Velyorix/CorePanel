<?php

namespace Core\Client\Navigation;

use Core\Auth\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

class ClientNavigation
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
                        'route' => 'client.dashboard',
                        'routeIs' => ['client.dashboard'],
                        'permission' => 'client.access',
                    ],
                    [
                        'label' => __('Catalog'),
                        'route' => 'client.catalog.index',
                        'routeIs' => ['client.catalog.*'],
                        'permission' => 'client.access',
                    ],
                    [
                        'label' => __('Cart'),
                        'route' => 'client.cart.index',
                        'routeIs' => ['client.cart.*'],
                        'permission' => 'client.access',
                    ],
                    [
                        'label' => __('Orders'),
                        'route' => 'client.orders.index',
                        'routeIs' => ['client.orders.*', 'client.checkout.placed'],
                        'permission' => 'client.orders.view',
                    ],
                    [
                        'label' => __('Services'),
                        'route' => null,
                        'permission' => 'client.services.view',
                    ],
                    [
                        'label' => __('Invoices'),
                        'route' => null,
                        'permission' => 'client.invoices.view',
                    ],
                    [
                        'label' => __('Payments'),
                        'route' => null,
                        'permission' => 'client.invoices.pay',
                    ],
                    [
                        'label' => __('Tickets'),
                        'route' => null,
                        'permission' => 'client.tickets.view',
                    ],
                ],
            ],
            [
                'label' => __('Account'),
                'items' => [
                    [
                        'label' => __('Profile'),
                        'route' => 'client.profile.edit',
                        'routeIs' => ['client.profile.*'],
                        'permission' => 'client.account.view',
                    ],
                    [
                        'label' => __('Security'),
                        'route' => 'client.profile.edit',
                        'routeIs' => ['client.profile.*'],
                        'permission' => 'client.account.manage',
                    ],
                    [
                        'label' => __('Guest users'),
                        'route' => null,
                        'permission' => 'client.account.manage',
                    ],
                    [
                        'label' => __('API Keys'),
                        'route' => null,
                        'permission' => 'client.account.manage',
                    ],
                ],
            ],
            [
                'label' => __('Marketplace'),
                'items' => [
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
                'label' => __('Support'),
                'items' => [
                    [
                        'label' => __('Open ticket'),
                        'route' => null,
                        'permission' => 'client.tickets.create',
                    ],
                    [
                        'label' => __('My tickets'),
                        'route' => null,
                        'permission' => 'client.tickets.view',
                    ],
                ],
            ],
            [
                'label' => __('Settings'),
                'items' => [
                    [
                        'label' => __('Notifications'),
                        'route' => null,
                        'permission' => 'client.account.manage',
                    ],
                    [
                        'label' => __('Preferences'),
                        'route' => null,
                        'permission' => 'client.account.manage',
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
