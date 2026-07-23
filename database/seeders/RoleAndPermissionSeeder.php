<?php

namespace Database\Seeders;

use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Core\Permissions\Services\PermissionService;
use Illuminate\Database\Seeder;

class RoleAndPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = collect($this->permissions())
            ->mapWithKeys(function (array $permission): array {
                $record = Permission::query()->updateOrCreate(
                    ['name' => $permission['name']],
                    [
                        'module' => $permission['module'],
                        'description' => $permission['description'],
                        'created_at' => now(),
                    ],
                );

                return [$permission['name'] => $record];
            });

        foreach ($this->roles() as $roleDefinition) {
            $role = Role::query()->updateOrCreate(
                ['name' => $roleDefinition['name']],
                [
                    'description' => $roleDefinition['description'],
                    'is_system' => true,
                ],
            );

            $permissionIds = collect($roleDefinition['permissions'])
                ->flatMap(function (string $pattern) use ($permissions): array {
                    if ($pattern === '*') {
                        return $permissions->pluck('id')->all();
                    }

                    if (str_ends_with($pattern, '.*')) {
                        $prefix = substr($pattern, 0, -1);

                        return $permissions
                            ->filter(fn (Permission $permission): bool => str_starts_with($permission->name, $prefix))
                            ->pluck('id')
                            ->all();
                    }

                    $permission = $permissions->get($pattern);

                    return $permission ? [$permission->id] : [];
                })
                ->unique()
                ->values()
                ->all();

            $role->permissions()->sync($permissionIds);
        }

        foreach ($this->roles() as $roleDefinition) {
            if (! array_key_exists('parent', $roleDefinition) || $roleDefinition['parent'] === null) {
                continue;
            }

            $role = Role::query()->where('name', $roleDefinition['name'])->firstOrFail();
            $parent = Role::query()->where('name', $roleDefinition['parent'])->firstOrFail();

            $role->update(['parent_id' => $parent->id]);
        }

        app(PermissionService::class)->forgetAll();
    }

    /**
     * @return list<array{name: string, module: string|null, description: string}>
     */
    private function permissions(): array
    {
        return [
            ['name' => 'admin.access', 'module' => 'core', 'description' => 'Access the admin panel'],
            ['name' => 'client.access', 'module' => 'core', 'description' => 'Access the client area'],

            ['name' => 'users.view', 'module' => 'core', 'description' => 'View users'],
            ['name' => 'users.view.own', 'module' => 'core', 'description' => 'View own user profile'],
            ['name' => 'users.create', 'module' => 'core', 'description' => 'Create users'],
            ['name' => 'users.update', 'module' => 'core', 'description' => 'Update users'],
            ['name' => 'users.update.own', 'module' => 'core', 'description' => 'Update own user profile'],
            ['name' => 'users.delete', 'module' => 'core', 'description' => 'Delete users'],

            ['name' => 'clients.view', 'module' => 'core', 'description' => 'View clients'],
            ['name' => 'clients.view.own', 'module' => 'core', 'description' => 'View own client account'],
            ['name' => 'clients.create', 'module' => 'core', 'description' => 'Create clients'],
            ['name' => 'clients.update', 'module' => 'core', 'description' => 'Update clients'],
            ['name' => 'clients.update.own', 'module' => 'core', 'description' => 'Update own client account'],
            ['name' => 'clients.delete', 'module' => 'core', 'description' => 'Delete clients'],
            ['name' => 'clients.impersonate', 'module' => 'core', 'description' => 'Impersonate clients'],

            ['name' => 'products.view', 'module' => 'products', 'description' => 'View products and categories'],
            ['name' => 'products.create', 'module' => 'products', 'description' => 'Create products and categories'],
            ['name' => 'products.update', 'module' => 'products', 'description' => 'Update products and categories'],
            ['name' => 'products.delete', 'module' => 'products', 'description' => 'Delete products and categories'],

            ['name' => 'orders.view', 'module' => 'orders', 'description' => 'View orders'],
            ['name' => 'orders.manage', 'module' => 'orders', 'description' => 'Manage order status and manual actions'],

            ['name' => 'roles.view', 'module' => 'core', 'description' => 'View roles and permissions'],
            ['name' => 'roles.manage', 'module' => 'core', 'description' => 'Manage roles and permissions'],

            ['name' => 'settings.view', 'module' => 'core', 'description' => 'View system settings'],
            ['name' => 'settings.manage', 'module' => 'core', 'description' => 'Manage system settings'],

            ['name' => 'audit.view', 'module' => 'core', 'description' => 'View audit logs'],

            ['name' => 'billing.invoices.view', 'module' => 'billing', 'description' => 'View invoices'],
            ['name' => 'billing.invoices.manage', 'module' => 'billing', 'description' => 'Manage invoices'],
            ['name' => 'billing.quotes.view', 'module' => 'billing', 'description' => 'View quotes'],
            ['name' => 'billing.quotes.manage', 'module' => 'billing', 'description' => 'Manage quotes'],
            ['name' => 'billing.payments.view', 'module' => 'billing', 'description' => 'View payments'],
            ['name' => 'billing.payments.manage', 'module' => 'billing', 'description' => 'Manage payments'],

            ['name' => 'services.view', 'module' => 'services', 'description' => 'View services'],
            ['name' => 'services.manage', 'module' => 'services', 'description' => 'Manage services'],

            ['name' => 'nodes.view', 'module' => 'nodes', 'description' => 'View infrastructure nodes'],
            ['name' => 'nodes.manage', 'module' => 'nodes', 'description' => 'Manage infrastructure nodes'],

            ['name' => 'modules.view', 'module' => 'modules', 'description' => 'View installed and discovered modules'],
            ['name' => 'modules.manage', 'module' => 'modules', 'description' => 'Install, enable, disable, and configure modules'],

            ['name' => 'themes.view', 'module' => 'themes', 'description' => 'View discovered themes'],
            ['name' => 'themes.manage', 'module' => 'themes', 'description' => 'Activate and preview themes'],

            ['name' => 'marketplace.view', 'module' => 'marketplace', 'description' => 'Browse marketplace catalogue and available updates'],
            ['name' => 'marketplace.manage', 'module' => 'marketplace', 'description' => 'Install marketplace packages and run update checks'],

            ['name' => 'tickets.view', 'module' => 'tickets', 'description' => 'View support tickets'],
            ['name' => 'tickets.reply', 'module' => 'tickets', 'description' => 'Reply to support tickets'],
            ['name' => 'tickets.assign', 'module' => 'tickets', 'description' => 'Assign support tickets'],
            ['name' => 'tickets.close', 'module' => 'tickets', 'description' => 'Close support tickets'],

            ['name' => 'client.services.view', 'module' => 'core', 'description' => 'View own services'],
            ['name' => 'client.services.manage', 'module' => 'core', 'description' => 'Manage own services'],
            ['name' => 'client.invoices.view', 'module' => 'core', 'description' => 'View own invoices'],
            ['name' => 'client.invoices.pay', 'module' => 'core', 'description' => 'Pay own invoices'],
            ['name' => 'client.quotes.view', 'module' => 'core', 'description' => 'View own quotes'],
            ['name' => 'client.orders.view', 'module' => 'core', 'description' => 'View own orders'],
            ['name' => 'client.tickets.view', 'module' => 'core', 'description' => 'View own tickets'],
            ['name' => 'client.tickets.create', 'module' => 'core', 'description' => 'Create support tickets'],
            ['name' => 'client.tickets.reply', 'module' => 'core', 'description' => 'Reply to own tickets'],
            ['name' => 'client.account.view', 'module' => 'core', 'description' => 'View own account'],
            ['name' => 'client.account.manage', 'module' => 'core', 'description' => 'Manage own account'],
        ];
    }

    /**
     * @return list<array{name: string, description: string, parent?: string|null, permissions: list<string>}>
     */
    private function roles(): array
    {
        return [
            [
                'name' => 'super-admin',
                'description' => 'Full system access',
                'parent' => 'admin',
                'permissions' => ['*'],
            ],
            [
                'name' => 'admin',
                'description' => 'Administrative access',
                'parent' => 'support',
                'permissions' => [
                    'admin.access',
                    'users.*',
                    'clients.*',
                    'products.*',
                    'orders.*',
                    'roles.view',
                    'settings.view',
                    'settings.manage',
                    'audit.view',
                    'billing.*',
                    'services.*',
                    'nodes.*',
                    'modules.*',
                    'themes.*',
                    'marketplace.*',
                    'tickets.*',
                ],
            ],
            [
                'name' => 'support',
                'description' => 'Support team access',
                'parent' => null,
                'permissions' => [
                    'admin.access',
                    'clients.view',
                    'products.view',
                    'orders.view',
                    'services.view',
                    'billing.invoices.view',
                    'billing.quotes.view',
                    'billing.payments.view',
                    'tickets.*',
                ],
            ],
            [
                'name' => 'client',
                'description' => 'Client area access',
                'parent' => null,
                'permissions' => [
                    'client.access',
                    'client.*',
                    'users.view.own',
                    'users.update.own',
                    'clients.view.own',
                    'clients.update.own',
                ],
            ],
        ];
    }
}
