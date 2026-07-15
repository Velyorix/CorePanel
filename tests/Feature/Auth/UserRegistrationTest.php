<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Core\Auth\Models\UserInvitation;
use Core\Auth\Services\RegistrationGate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.password.check_compromised' => false,
            'session.driver' => 'array',
        ]);
    }

    public function test_open_registration_creates_user_with_default_role(): void
    {
        config(['corepanel.auth.registration.mode' => 'open']);

        $response = $this->post('/register', $this->validRegistrationPayload([
            'email' => 'open@corepanel.test',
        ]));

        $response->assertRedirect('/');

        $user = User::query()->where('email', 'open@corepanel.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->roles->contains('name', 'client'));
    }

    public function test_invite_only_mode_blocks_public_register_routes(): void
    {
        config(['corepanel.auth.registration.mode' => 'invite']);

        $this->get('/register')->assertNotFound();
        $this->post('/register', $this->validRegistrationPayload())->assertNotFound();
    }

    public function test_invitation_registration_creates_user_and_marks_invitation_accepted(): void
    {
        config(['corepanel.auth.registration.mode' => 'invite']);

        [$invitation, $plainToken] = $this->createInvitation('invite@corepanel.test');

        $response = $this->post("/register/invitation/{$plainToken}", $this->validRegistrationPayload([
            'email' => 'invite@corepanel.test',
        ]));

        $response->assertRedirect('/');

        $user = User::query()->where('email', 'invite@corepanel.test')->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->roles->contains('name', 'client'));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_invitation_registration_assigns_invited_role(): void
    {
        config(['corepanel.auth.registration.mode' => 'invite']);

        [$invitation, $plainToken] = $this->createInvitation('admin-invite@corepanel.test', 'admin');

        $response = $this->post("/register/invitation/{$plainToken}", $this->validRegistrationPayload([
            'email' => 'admin-invite@corepanel.test',
            'name' => 'Invited Admin',
        ]));

        $response->assertRedirect('/');

        $user = User::query()->where('email', 'admin-invite@corepanel.test')->firstOrFail();

        $this->assertTrue($user->roles->contains('name', 'admin'));
        $this->assertNotNull($invitation->fresh()->accepted_at);
    }

    public function test_invalid_invitation_token_redirects_to_login(): void
    {
        config(['corepanel.auth.registration.mode' => 'invite']);

        $this->get('/register/invitation/invalid-token')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
    }

    public function test_expired_invitation_cannot_be_used_for_registration(): void
    {
        config(['corepanel.auth.registration.mode' => 'invite']);

        [, $plainToken] = $this->createInvitation('expired@corepanel.test', expired: true);

        $this->get("/register/invitation/{$plainToken}")
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', [
            'email' => 'expired@corepanel.test',
        ]);
    }

    /**
     * @return array{0: UserInvitation, 1: string}
     */
    private function createInvitation(
        string $email,
        ?string $roleName = null,
        bool $expired = false,
    ): array {
        $gate = app(RegistrationGate::class);
        $plainToken = $gate->generateToken();

        $factory = UserInvitation::factory()->state([
            'email' => $email,
            'token' => $gate->hashToken($plainToken),
        ]);

        if ($roleName !== null) {
            $factory = $factory->withRole($roleName);
        }

        if ($expired) {
            $factory = $factory->expired();
        }

        return [$factory->create(), $plainToken];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, string>
     */
    private function validRegistrationPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jane Doe',
            'email' => 'jane@corepanel.test',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ], $overrides);
    }
}
