<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Core\Clients\Models\Client;
use Core\Permissions\Models\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthorizationPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config(['corepanel.rbac.cache.enabled' => false]);
    }

    public function test_super_admin_can_manage_users_roles_and_clients(): void
    {
        $superAdmin = User::factory()->withRole('super-admin')->create();
        $targetUser = User::factory()->create();
        $role = Role::query()->where('name', 'client')->firstOrFail();
        $client = Client::factory()->create();

        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $targetUser));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', Role::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $role));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('impersonate', $client));
    }

    public function test_admin_can_manage_users_and_clients_but_not_system_roles(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $targetUser = User::factory()->create();
        $systemRole = Role::query()->where('name', 'super-admin')->firstOrFail();
        $client = Client::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('create', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $targetUser));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $client));
        $this->assertTrue(Gate::forUser($admin)->allows('impersonate', $client));

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Role::class));
        $this->assertFalse(Gate::forUser($admin)->allows('create', Role::class));
        $this->assertFalse(Gate::forUser($admin)->allows('delete', $systemRole));
    }

    public function test_support_user_has_limited_client_and_user_access(): void
    {
        $support = User::factory()->withRole('support')->create();
        $targetUser = User::factory()->create();
        $client = Client::factory()->create();
        $role = Role::query()->where('name', 'client')->firstOrFail();

        $this->assertFalse(Gate::forUser($support)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($support)->allows('update', $targetUser));
        $this->assertTrue(Gate::forUser($support)->allows('view', $client));
        $this->assertFalse(Gate::forUser($support)->allows('create', Client::class));
        $this->assertFalse(Gate::forUser($support)->allows('impersonate', $client));
        $this->assertFalse(Gate::forUser($support)->allows('viewAny', Role::class));
        $this->assertFalse(Gate::forUser($support)->allows('update', $role));
    }

    public function test_client_user_cannot_access_admin_resource_policies(): void
    {
        $clientUser = User::factory()->withRole('client')->create();
        $targetUser = User::factory()->create();
        $client = Client::factory()->create();
        $role = Role::query()->where('name', 'admin')->firstOrFail();

        $this->assertFalse(Gate::forUser($clientUser)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($clientUser)->allows('view', $targetUser));
        $this->assertFalse(Gate::forUser($clientUser)->allows('viewAny', Client::class));
        $this->assertFalse(Gate::forUser($clientUser)->allows('update', $client));
        $this->assertFalse(Gate::forUser($clientUser)->allows('viewAny', Role::class));
        $this->assertFalse(Gate::forUser($clientUser)->allows('view', $role));
    }

    public function test_client_user_can_manage_own_profile_and_owned_client(): void
    {
        $clientUser = User::factory()->withRole('client')->create();
        $ownedClient = Client::factory()->create(['user_id' => $clientUser->id]);

        $this->assertTrue(Gate::forUser($clientUser)->allows('view', $clientUser));
        $this->assertTrue(Gate::forUser($clientUser)->allows('update', $clientUser));
        $this->assertTrue(Gate::forUser($clientUser)->allows('view', $ownedClient));
        $this->assertTrue(Gate::forUser($clientUser)->allows('update', $ownedClient));
    }

    public function test_system_roles_cannot_be_deleted_even_with_manage_permission(): void
    {
        $superAdmin = User::factory()->withRole('super-admin')->create();
        $systemRole = Role::query()->where('name', 'admin')->firstOrFail();

        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $systemRole));
        $this->assertFalse(Gate::forUser($superAdmin)->allows('delete', $systemRole));
    }
}
