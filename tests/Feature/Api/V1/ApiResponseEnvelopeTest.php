<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use Core\API\Services\ApiTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApiResponseEnvelopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ping_success_uses_data_meta_envelope(): void
    {
        $response = $this->getJson(route('v1.ping'), [
            'X-Request-Id' => 'envelope-ping-1',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.message', 'pong')
            ->assertJsonPath('meta.request_id', 'envelope-ping-1')
            ->assertJsonMissingPath('ok')
            ->assertJsonMissingPath('error');
    }

    public function test_me_success_uses_data_meta_envelope(): void
    {
        $user = User::factory()->create([
            'email' => 'envelope@example.test',
        ]);
        $issued = app(ApiTokenService::class)->issue($user, 'Envelope');

        $this->withToken($issued['plain_text'])
            ->getJson(route('v1.me'))
            ->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('data.email', 'envelope@example.test')
            ->assertJsonMissingPath('error')
            ->assertJsonStructure(['data', 'meta' => ['request_id']]);
    }

    public function test_unknown_v1_route_returns_not_found_envelope(): void
    {
        $this->getJson('/api/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found')
            ->assertJsonMissingPath('data');
    }

    public function test_validation_exception_returns_validation_failed_envelope(): void
    {
        Route::middleware(['api', 'api.v1'])->post('/api/v1/_validation-probe', function () {
            throw ValidationException::withMessages([
                'name' => ['The name field is required.'],
            ]);
        });

        $this->postJson('/api/v1/_validation-probe', [])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonPath('error.details.fields.name.0', 'The name field is required.')
            ->assertJsonMissingPath('data');
    }

    public function test_error_responses_never_include_data_key(): void
    {
        $this->getJson(route('v1.me'))
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'unauthenticated')
            ->assertJsonMissingPath('data')
            ->assertJsonMissingPath('meta');
    }
}
