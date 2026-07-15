<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class BladeAuthorizationDirectivesTest extends TestCase
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

    public function test_permission_blade_directive_renders_for_authorized_user(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin);

        $output = Blade::render('@permission("admin.access")<span>admin-menu</span>@endpermission');

        $this->assertStringContainsString('admin-menu', $output);
    }

    public function test_permission_blade_directive_hides_content_for_unauthorized_user(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client);

        $output = Blade::render('@permission("admin.access")<span>admin-menu</span>@endpermission');

        $this->assertStringNotContainsString('admin-menu', $output);
    }

    public function test_can_blade_directive_works_with_dynamic_permission_gates(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin);

        $output = Blade::render('@can("users.delete")<span>delete-user</span>@endcan');

        $this->assertStringContainsString('delete-user', $output);
    }

    public function test_role_and_anyrole_blade_directives(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin);

        $roleOutput = Blade::render('@role("admin")<span>admin-role</span>@endrole');
        $anyRoleOutput = Blade::render('@anyrole("support", "admin")<span>staff-role</span>@endanyrole');

        $this->assertStringContainsString('admin-role', $roleOutput);
        $this->assertStringContainsString('staff-role', $anyRoleOutput);
    }

    public function test_anypermission_and_allpermissions_blade_directives(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin);

        $anyOutput = Blade::render('@anypermission("users.delete", "missing.permission")<span>any-perm</span>@endanypermission');
        $allOutput = Blade::render('@allpermissions("users.view", "users.update")<span>all-perm</span>@endallpermissions');
        $missingAllOutput = Blade::render('@allpermissions("users.view", "missing.permission")<span>missing-all</span>@endallpermissions');

        $this->assertStringContainsString('any-perm', $anyOutput);
        $this->assertStringContainsString('all-perm', $allOutput);
        $this->assertStringNotContainsString('missing-all', $missingAllOutput);
    }
}
