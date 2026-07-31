<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Core\Clients\Models\Client;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class DynamicGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => false,
            'corepanel.rbac.permissions_registry.cache_enabled' => false,
        ]);
    }

    public function test_can_authorizes_known_permissions_via_dynamic_gate(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = User::factory()->withRole('client')->create();

        $this->assertTrue(Gate::forUser($admin)->allows('admin.access'));
        $this->assertTrue(Gate::forUser($admin)->allows('users.delete'));
        $this->assertFalse(Gate::forUser($client)->allows('admin.access'));
        $this->assertFalse(Gate::forUser($client)->allows('users.delete'));
    }

    public function test_dynamic_gate_does_not_override_policy_abilities(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $targetUser = User::factory()->create();
        $client = Client::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', User::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $targetUser));
        $this->assertTrue(Gate::forUser($admin)->allows('impersonate', $client));
    }

    public function test_unknown_abilities_fall_through_to_default_gate_behavior(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->assertFalse(Gate::forUser($admin)->allows('not-a-permission'));
    }
}
