<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-profile',
            'corepanel.auth.email_verification.required' => false,
            'corepanel.password.check_compromised' => false,
        ]);
    }

    public function test_admin_can_view_profile_page(): void
    {
        $admin = User::factory()->withRole('admin')->create([
            'name' => 'Admin User',
            'email' => 'admin@corepanel.test',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.profile.edit'))
            ->assertOk()
            ->assertSee(__('Profile information'), false)
            ->assertSee(__('Change password'), false)
            ->assertSee('Admin User', false)
            ->assertSee('admin@corepanel.test', false)
            ->assertSee(route('admin.profile.update'), false)
            ->assertSee(route('admin.profile.password.update'), false);
    }

    public function test_support_user_can_view_and_update_own_profile(): void
    {
        $support = User::factory()->withRole('support')->create([
            'name' => 'Support Agent',
            'email' => 'support@corepanel.test',
        ]);

        $this->actingAs($support)
            ->get(route('admin.profile.edit'))
            ->assertOk();

        $this->actingAs($support)
            ->from(route('admin.profile.edit'))
            ->put(route('admin.profile.update'), [
                'name' => 'Support Updated',
                'email' => 'support.updated@corepanel.test',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHas('status');

        $support->refresh();

        $this->assertSame('Support Updated', $support->name);
        $this->assertSame('support.updated@corepanel.test', $support->email);
    }

    public function test_admin_can_update_profile_details(): void
    {
        $admin = User::factory()->withRole('admin')->create([
            'name' => 'Before Name',
            'email' => 'before@corepanel.test',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.profile.edit'))
            ->put(route('admin.profile.update'), [
                'name' => 'After Name',
                'email' => 'after@corepanel.test',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHas('status', __('Profile updated successfully.'));

        $admin->refresh();

        $this->assertSame('After Name', $admin->name);
        $this->assertSame('after@corepanel.test', $admin->email);
    }

    public function test_profile_update_rejects_duplicate_email(): void
    {
        $admin = User::factory()->withRole('admin')->create([
            'email' => 'admin@corepanel.test',
        ]);

        User::factory()->create([
            'email' => 'taken@corepanel.test',
        ]);

        $this->actingAs($admin)
            ->from(route('admin.profile.edit'))
            ->put(route('admin.profile.update'), [
                'name' => $admin->name,
                'email' => 'taken@corepanel.test',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHasErrors('email');
    }

    public function test_admin_can_change_password_with_valid_current_password(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->from(route('admin.profile.edit'))
            ->put(route('admin.profile.password.update'), [
                'current_password' => 'password',
                'password' => 'NewSecurePass123!',
                'password_confirmation' => 'NewSecurePass123!',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHas('password_status', __('Password updated successfully.'));

        $this->assertTrue(Hash::check('NewSecurePass123!', $admin->fresh()->password));
    }

    public function test_password_change_rejects_invalid_current_password(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->from(route('admin.profile.edit'))
            ->put(route('admin.profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'NewSecurePass123!',
                'password_confirmation' => 'NewSecurePass123!',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $admin->fresh()->password));
    }

    public function test_password_change_rejects_weak_password(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->from(route('admin.profile.edit'))
            ->put(route('admin.profile.password.update'), [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHasErrors('password');
    }

    public function test_client_cannot_access_admin_profile_page(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->get(route('admin.profile.edit'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_profile_page(): void
    {
        $this->get(route('admin.profile.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_topbar_links_to_profile_page(): void
    {
        $admin = User::factory()->withRole('admin')->create([
            'name' => 'Topbar Admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee(route('admin.profile.edit'), false)
            ->assertSee('Topbar Admin', false);
    }

    public function test_email_change_requires_reverification_when_enabled(): void
    {
        config(['corepanel.auth.email_verification.required' => true]);

        $admin = User::factory()->withRole('admin')->create([
            'email' => 'verified@corepanel.test',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->from(route('admin.profile.edit'))
            ->put(route('admin.profile.update'), [
                'name' => $admin->name,
                'email' => 'new-email@corepanel.test',
            ])
            ->assertRedirect(route('admin.profile.edit'))
            ->assertSessionHas('status', __('Profile updated. Please verify your new email address.'));

        $admin->refresh();

        $this->assertSame('new-email@corepanel.test', $admin->email);
        $this->assertNull($admin->email_verified_at);
    }
}
