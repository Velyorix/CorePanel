<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Core\Clients\Models\Client;
use Core\Permissions\Services\PermissionService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ContextualPermissionTest extends TestCase
{
    use RefreshDatabase;

    private PermissionService $permissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => false,
            'corepanel.rbac.permissions_registry.cache_enabled' => false,
        ]);

        $this->permissionService = app(PermissionService::class);
    }

    public function test_any_scope_allows_global_access_without_ownership(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $targetUser = User::factory()->create();
        $client = Client::factory()->create();

        $this->assertTrue($this->permissionService->userCan($admin, 'users.view', $targetUser));
        $this->assertTrue($this->permissionService->userCan($admin, 'clients.view', $client));
        $this->assertTrue($this->permissionService->userCan($admin, 'users.view'));
    }

    public function test_own_scope_allows_access_only_to_owned_resources(): void
    {
        $clientUser = User::factory()->withRole('client')->create();
        $otherUser = User::factory()->create();
        $ownedClient = Client::factory()->create(['user_id' => $clientUser->id]);
        $otherClient = Client::factory()->create();

        $this->assertTrue($this->permissionService->userCan($clientUser, 'users.view', $clientUser));
        $this->assertFalse($this->permissionService->userCan($clientUser, 'users.view', $otherUser));
        $this->assertTrue($this->permissionService->userCan($clientUser, 'clients.view', $ownedClient));
        $this->assertFalse($this->permissionService->userCan($clientUser, 'clients.view', $otherClient));
    }

    public function test_own_scope_does_not_grant_list_access(): void
    {
        $clientUser = User::factory()->withRole('client')->create();

        $this->assertFalse($this->permissionService->userCan($clientUser, 'users.view'));
        $this->assertFalse(Gate::forUser($clientUser)->allows('viewAny', User::class));
        $this->assertFalse(Gate::forUser($clientUser)->allows('viewAny', Client::class));
    }

    public function test_client_user_can_view_and_update_own_profile_via_policy(): void
    {
        $clientUser = User::factory()->withRole('client')->create();
        $otherUser = User::factory()->create();

        $this->assertTrue(Gate::forUser($clientUser)->allows('view', $clientUser));
        $this->assertTrue(Gate::forUser($clientUser)->allows('update', $clientUser));
        $this->assertFalse(Gate::forUser($clientUser)->allows('view', $otherUser));
        $this->assertFalse(Gate::forUser($clientUser)->allows('update', $otherUser));
    }

    public function test_client_member_can_access_owned_client_with_own_scope(): void
    {
        $member = User::factory()->withRole('client')->create();
        $client = Client::factory()->create();
        $member->clients()->attach($client->id, [
            'role' => 'member',
            'permissions' => null,
            'created_at' => now(),
        ]);

        $this->assertTrue($this->permissionService->userCan($member, 'clients.view', $client));
        $this->assertTrue($this->permissionService->userCan($member, 'clients.update', $client));
    }
}
