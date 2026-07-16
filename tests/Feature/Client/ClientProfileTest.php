<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ClientProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-profile',
            'corepanel.auth.email_verification.required' => false,
            'corepanel.password.check_compromised' => false,
        ]);
    }

    public function test_client_can_view_profile_page(): void
    {
        $client = User::factory()->withRole('client')->create([
            'name' => 'Client User',
            'email' => 'client@corepanel.test',
        ]);

        $this->actingAs($client)
            ->get(route('client.profile.edit'))
            ->assertOk()
            ->assertSee(__('Profile information'), false)
            ->assertSee(__('Change password'), false)
            ->assertSee('Client User', false)
            ->assertSee('client@corepanel.test', false)
            ->assertSee(route('client.profile.update'), false)
            ->assertSee(route('client.profile.password.update'), false);
    }

    public function test_client_can_update_profile_details(): void
    {
        $client = User::factory()->withRole('client')->create([
            'name' => 'Before Name',
            'email' => 'before@corepanel.test',
        ]);

        $this->actingAs($client)
            ->from(route('client.profile.edit'))
            ->put(route('client.profile.update'), [
                'name' => 'After Name',
                'email' => 'after@corepanel.test',
            ])
            ->assertRedirect(route('client.profile.edit'))
            ->assertSessionHas('status', __('Profile updated successfully.'));

        $client->refresh();

        $this->assertSame('After Name', $client->name);
        $this->assertSame('after@corepanel.test', $client->email);
    }

    public function test_profile_update_rejects_duplicate_email(): void
    {
        $client = User::factory()->withRole('client')->create([
            'email' => 'client@corepanel.test',
        ]);

        User::factory()->create([
            'email' => 'taken@corepanel.test',
        ]);

        $this->actingAs($client)
            ->from(route('client.profile.edit'))
            ->put(route('client.profile.update'), [
                'name' => $client->name,
                'email' => 'taken@corepanel.test',
            ])
            ->assertRedirect(route('client.profile.edit'))
            ->assertSessionHasErrors('email');
    }

    public function test_client_can_change_password_with_valid_current_password(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->from(route('client.profile.edit'))
            ->put(route('client.profile.password.update'), [
                'current_password' => 'password',
                'password' => 'NewSecurePass123!',
                'password_confirmation' => 'NewSecurePass123!',
            ])
            ->assertRedirect(route('client.profile.edit'))
            ->assertSessionHas('password_status', __('Password updated successfully.'));

        $this->assertTrue(Hash::check('NewSecurePass123!', $client->fresh()->password));
    }

    public function test_password_change_rejects_invalid_current_password(): void
    {
        $client = User::factory()->withRole('client')->create();

        $this->actingAs($client)
            ->from(route('client.profile.edit'))
            ->put(route('client.profile.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'NewSecurePass123!',
                'password_confirmation' => 'NewSecurePass123!',
            ])
            ->assertRedirect(route('client.profile.edit'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $client->fresh()->password));
    }

    public function test_guest_is_redirected_from_client_profile_page(): void
    {
        $this->get(route('client.profile.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_is_forbidden_from_client_profile_page(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.profile.edit'))
            ->assertForbidden();
    }
}

