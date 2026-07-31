<?php

namespace Tests\Feature\Api\V1;

use Tests\TestCase;

class ApiMiddlewareStackTest extends TestCase
{
    public function test_v1_ping_route_is_registered(): void
    {
        $this->assertSame(
            url('/api/v1/ping'),
            route('v1.ping'),
        );
    }

    public function test_v1_ping_returns_json_with_expected_headers(): void
    {
        $response = $this->getJson(route('v1.ping'));

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'status' => 'ok',
                    'message' => 'pong',
                    'api_version' => 'v1',
                ],
            ])
            ->assertHeader('X-Api-Version', 'v1')
            ->assertHeader('Content-Type', 'application/json');

        $this->assertNotEmpty($response->headers->get('X-Request-Id'));
        $this->assertSame(
            $response->headers->get('X-Request-Id'),
            $response->json('meta.request_id'),
        );
    }

    public function test_v1_ping_echoes_client_request_id(): void
    {
        $response = $this->getJson(route('v1.ping'), [
            'X-Request-Id' => 'test-request-id-123',
        ]);

        $response
            ->assertOk()
            ->assertHeader('X-Request-Id', 'test-request-id-123')
            ->assertJsonPath('meta.request_id', 'test-request-id-123');
    }

    public function test_v1_rejects_non_json_body_on_post(): void
    {
        $this->call(
            'POST',
            '/api/v1/ping',
            [],
            [],
            [],
            [
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'text/plain',
            ],
            'not-json',
        )
            ->assertStatus(415)
            ->assertJsonPath('error.code', 'unsupported_media_type');
    }

    public function test_maintenance_mode_returns_json_for_v1(): void
    {
        config([
            'corepanel.maintenance.enabled' => true,
            'corepanel.maintenance.message' => 'API offline for maintenance.',
            'corepanel.maintenance.except' => ['up'],
        ]);

        $this->getJson(route('v1.ping'))
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'maintenance_mode')
            ->assertJsonPath('error.message', 'API offline for maintenance.');
    }

    public function test_set_locale_via_x_locale_header(): void
    {
        config([
            'corepanel.locale.supported' => ['en', 'fr'],
            'app.locale' => 'en',
        ]);

        $this->getJson(route('v1.ping'), [
            'X-Locale' => 'fr',
        ])->assertOk();

        $this->assertSame('fr', app()->getLocale());
    }

    public function test_webhook_route_stays_outside_v1_stack(): void
    {
        $this->assertSame(
            url('/api/webhooks/payments/fake'),
            route('webhooks.payments', ['gateway' => 'fake']),
        );

        $this->assertStringNotContainsString('/v1/', route('webhooks.payments', ['gateway' => 'fake']));
    }
}
