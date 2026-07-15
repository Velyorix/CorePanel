<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.auth.email_verification.required' => false,
            'corepanel.auth.audit.enabled' => false,
            'corepanel.auth.lockout.enabled' => false,
            'corepanel.auth.login.max_attempts' => 3,
            'corepanel.auth.login.decay_seconds' => 60,
            'session.driver' => 'array',
        ]);
    }

    public function test_login_is_rate_limited_after_max_attempts(): void
    {
        User::factory()->create([
            'email' => 'ratelimit@corepanel.test',
            'password' => 'password',
        ]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->from(route('login'))
                ->post('/login', [
                    'email' => 'ratelimit@corepanel.test',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('login'));
        }

        $this->from(route('login'))
            ->post('/login', [
                'email' => 'ratelimit@corepanel.test',
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_successful_login_clears_rate_limiter(): void
    {
        User::factory()->create([
            'email' => 'clear-limit@corepanel.test',
            'password' => 'password',
        ]);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->post('/login', [
                'email' => 'clear-limit@corepanel.test',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/login', [
            'email' => 'clear-limit@corepanel.test',
            'password' => 'password',
        ])->assertRedirect('/');

        $this->post('/logout');

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->from(route('login'))
                ->post('/login', [
                    'email' => 'clear-limit@corepanel.test',
                    'password' => 'wrong-password',
                ])
                ->assertRedirect(route('login'))
                ->assertSessionHasErrors('email');
        }

        $this->from(route('login'))
            ->post('/login', [
                'email' => 'clear-limit@corepanel.test',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Too many login attempts',
            (string) session('errors')?->first('email'),
        );
    }
}
