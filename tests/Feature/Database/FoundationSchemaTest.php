<?php

namespace Tests\Feature\Database;

use Core\Auth\Models\User;
use Core\Permissions\Models\Permission;
use Core\Permissions\Models\Role;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function foundationTables(): array
    {
        return [
            'users',
            'user_sessions',
            'password_resets',
            'sessions',
            'roles',
            'permissions',
            'role_permissions',
            'user_roles',
            'user_permissions',
            'clients',
            'client_users',
            'settings',
            'audit_logs',
        ];
    }

    public function test_foundation_migrations_create_expected_tables(): void
    {
        foreach ($this->foundationTables() as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }
    }

    public function test_user_factory_persists_user_with_foundation_columns(): void
    {
        $user = User::factory()->create([
            'email' => 'foundation@corepanel.test',
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'foundation@corepanel.test',
            'status' => 'active',
            'two_factor_enabled' => false,
        ]);

        $this->assertNotNull($user->password);
        $this->assertTrue($user->password !== 'password');
    }

    public function test_user_factory_supports_suspended_and_two_factor_states(): void
    {
        $suspendedUser = User::factory()->suspended()->create();
        $twoFactorUser = User::factory()->withTwoFactor()->create();

        $this->assertSame('suspended', $suspendedUser->status);
        $this->assertTrue($twoFactorUser->two_factor_enabled);
        $this->assertNotNull($twoFactorUser->two_factor_secret);
    }

    public function test_user_supports_soft_delete(): void
    {
        $user = User::factory()->create();

        $user->delete();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_role_and_permission_seeder_creates_system_roles(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $this->assertDatabaseCount('roles', 4);
        $this->assertDatabaseCount('permissions', 44);

        $this->assertDatabaseHas('roles', [
            'name' => 'super-admin',
            'is_system' => true,
        ]);

        $this->assertDatabaseHas('roles', [
            'name' => 'client',
            'is_system' => true,
        ]);
    }

    public function test_super_admin_role_has_all_permissions(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $superAdmin = Role::query()->where('name', 'super-admin')->firstOrFail();
        $permissionCount = Permission::query()->count();

        $this->assertSame($permissionCount, $superAdmin->permissions()->count());
    }

    public function test_user_factory_can_assign_role(): void
    {
        $this->seed(RoleAndPermissionSeeder::class);

        $user = User::factory()->withRole('admin')->create();

        $this->assertTrue(
            $user->fresh()->roles->contains('name', 'admin'),
        );
    }
}
