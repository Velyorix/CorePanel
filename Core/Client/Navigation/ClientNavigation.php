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
                        'route' => 'client.services.index',
                        'routeIs' => ['client.services.*'],
                        'permission' => 'client.services.view',
                    ],
                    [
                        'label' => __('Invoices'),
                        'route' => 'client.invoices.index',
                        'routeIs' => ['client.invoices.*'],
                        'permission' => 'client.invoices.view',
                    ],
                    [
                        'label' => __('Quotes'),
                        'route' => 'client.quotes.index',
                        'routeIs' => ['client.quotes.*'],
                        'permission' => 'client.quotes.view',
                    ],
                    [
                        'label' => __('Payments'),
                        'route' => 'client.payments.index',
                        'routeIs' => ['client.payments.*'],
                        'permission' => 'client.invoices.pay',
                    ],
                    [
                        'label' => __('Tickets'),
                        'route' => 'client.tickets.index',
                        'routeIs' => ['client.tickets.*'],
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
                        'route' => 'client.marketplace.index',
                        'routeParams' => ['product_type' => 'module'],
                        'routeIs' => ['client.marketplace.*'],
                        'permission' => 'client.marketplace.view',
                    ],
                    [
                        'label' => __('Themes'),
                        'route' => 'client.marketplace.index',
                        'routeParams' => ['product_type' => 'theme'],
                        'routeIs' => ['client.marketplace.*'],
                        'permission' => 'client.marketplace.view',
                    ],
                    [
                        'label' => __('Purchases'),
                        'route' => 'client.marketplace.purchases',
                        'routeIs' => ['client.marketplace.purchases'],
                        'permission' => 'client.marketplace.view',
                    ],
                ],
            ],
            [
                'label' => __('Support'),
                'items' => [
                    [
                        'label' => __('Open ticket'),
                        'route' => 'client.tickets.create',
                        'routeIs' => ['client.tickets.create', 'client.tickets.store'],
                        'permission' => 'client.tickets.create',
                    ],
                    [
                        'label' => __('My tickets'),
                        'route' => 'client.tickets.index',
                        'routeIs' => ['client.tickets.index', 'client.tickets.show'],
                        'permission' => 'client.tickets.view',
                    ],
                    [
                        'label' => __('Knowledge base'),
                        'route' => 'client.kb.index',
                        'routeIs' => ['client.kb.*'],
                        'permission' => 'client.kb.view',
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
        $routeParams = is_array($item['routeParams'] ?? null) ? $item['routeParams'] : [];
        $url = filled($routeName) && Route::has($routeName)
            ? route($routeName, $routeParams)
            : null;

        $routeIs = $item['routeIs'] ?? (filled($routeName) ? [$routeName] : []);

        $active = $routeParams !== []
            ? filled($routeName)
                && request()->routeIs($routeName)
                && collect($routeParams)->every(
                    static fn (mixed $value, string|int $key): bool => (string) request()->query((string) $key) === (string) $value,
                )
            : $this->isActive($routeIs);

        return [
            'label' => $item['label'],
            'url' => $url,
            'active' => $active,
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
