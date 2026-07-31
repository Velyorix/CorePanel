<?php

namespace Tests\Feature\Clients;

use App\Models\User;
use Core\Clients\Enums\ClientStatus;
use Core\Clients\Models\Client;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClientListFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-client-list',
        ]);
    }

    public function test_admin_can_search_clients_by_company_and_owner_email(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $owner = User::factory()->create(['email' => 'owner-search@corepanel.test']);

        Client::factory()->create([
            'company_name' => 'Alpha Hosting',
            'user_id' => $owner->id,
            'status' => ClientStatus::Active,
        ]);
        Client::factory()->create([
            'company_name' => 'Beta Cloud',
            'status' => ClientStatus::Active,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clients.index', ['q' => 'Alpha']))
            ->assertOk()
            ->assertSee('Alpha Hosting')
            ->assertDontSee('Beta Cloud');

        $this->actingAs($admin)
            ->get(route('admin.clients.index', ['q' => 'owner-search@corepanel.test']))
            ->assertOk()
            ->assertSee('Alpha Hosting')
            ->assertDontSee('Beta Cloud');
    }

    public function test_admin_can_filter_clients_by_status(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        Client::factory()->create([
            'company_name' => 'Active Co',
            'status' => ClientStatus::Active,
        ]);
        Client::factory()->create([
            'company_name' => 'Suspended Co',
            'status' => ClientStatus::Suspended,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.clients.index', ['status' => ClientStatus::Suspended->value]))
            ->assertOk()
            ->assertSee('Suspended Co')
            ->assertDontSee('Active Co');
    }

    public function test_admin_can_sort_clients_by_company_name(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        Client::factory()->create(['company_name' => 'Zebra Co', 'status' => ClientStatus::Active]);
        Client::factory()->create(['company_name' => 'Alpha Co', 'status' => ClientStatus::Active]);

        $response = $this->actingAs($admin)
            ->get(route('admin.clients.index', [
                'sort' => 'company_name',
                'dir' => 'asc',
            ]))
            ->assertOk();

        $content = $response->getContent();
        $alphaPos = strpos($content, 'Alpha Co');
        $zebraPos = strpos($content, 'Zebra Co');

        $this->assertNotFalse($alphaPos);
        $this->assertNotFalse($zebraPos);
        $this->assertLessThan($zebraPos, $alphaPos);
    }

    public function test_invalid_filter_values_are_rejected(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.clients.index', [
                'status' => 'unknown',
                'sort' => 'password',
                'dir' => 'sideways',
            ]))
            ->assertSessionHasErrors(['status', 'sort', 'dir']);
    }

    public function test_filter_form_is_rendered_on_index(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('name="q"', false)
            ->assertSee('name="status"', false)
            ->assertSee('sort=company_name', false);
    }
}
