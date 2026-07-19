<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Enums\QuoteStatus;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\InvoiceItem;
use Core\Billing\Models\Payment;
use Core\Billing\Models\Quote;
use Core\Billing\Models\QuoteItem;
use Core\Clients\Enums\ClientMembershipRole;
use Core\Clients\Models\Client;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBillingUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.client-billing',
        ]);
    }

    public function test_client_can_view_own_invoices_index_and_show(): void
    {
        [$user, $client] = $this->makeClientUser();
        $invoice = Invoice::factory()->unpaid()->create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-CLIENT-0001',
            'total_amount' => '42.50',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_name' => 'VPS Starter',
            'line_total' => '42.50',
        ]);

        $this->actingAs($user)
            ->get(route('client.invoices.index'))
            ->assertOk()
            ->assertSee('INV-CLIENT-0001');

        $this->actingAs($user)
            ->get(route('client.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('INV-CLIENT-0001')
            ->assertSee('VPS Starter');
    }

    public function test_client_cannot_view_another_clients_invoice(): void
    {
        [$user] = $this->makeClientUser();
        $other = Client::factory()->create();
        $invoice = Invoice::factory()->unpaid()->create(['client_id' => $other->id]);

        $this->actingAs($user)
            ->get(route('client.invoices.show', $invoice))
            ->assertNotFound();
    }

    public function test_client_cannot_view_draft_invoice(): void
    {
        [$user, $client] = $this->makeClientUser();
        $invoice = Invoice::factory()->draft()->create(['client_id' => $client->id]);

        $this->actingAs($user)
            ->get(route('client.invoices.show', $invoice))
            ->assertNotFound();
    }

    public function test_client_can_download_invoice_pdf(): void
    {
        [$user, $client] = $this->makeClientUser();
        $invoice = Invoice::factory()->unpaid()->create(['client_id' => $client->id]);
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $response = $this->actingAs($user)->get(route('client.invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_client_can_pay_unpaid_invoice(): void
    {
        [$user, $client] = $this->makeClientUser();
        $invoice = Invoice::factory()->unpaid()->create([
            'client_id' => $client->id,
            'total_amount' => '60.00',
        ]);

        $this->actingAs($user)
            ->post(route('client.invoices.pay', $invoice))
            ->assertRedirect(route('client.invoices.show', $invoice))
            ->assertSessionHas('status');

        $payment = Payment::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame($client->id, $payment->client_id);
    }

    public function test_client_cannot_pay_another_clients_invoice(): void
    {
        [$user] = $this->makeClientUser();
        $other = Client::factory()->create();
        $invoice = Invoice::factory()->unpaid()->create(['client_id' => $other->id]);

        $this->actingAs($user)
            ->post(route('client.invoices.pay', $invoice))
            ->assertNotFound();
    }

    public function test_client_can_view_own_quotes_index_and_show(): void
    {
        [$user, $client] = $this->makeClientUser();
        $quote = Quote::factory()->sent()->create([
            'client_id' => $client->id,
            'quote_number' => 'QUO-CLIENT-0001',
        ]);
        QuoteItem::factory()->create([
            'quote_id' => $quote->id,
            'product_name' => 'Cloud Backup',
        ]);

        $this->actingAs($user)
            ->get(route('client.quotes.index'))
            ->assertOk()
            ->assertSee('QUO-CLIENT-0001');

        $this->actingAs($user)
            ->get(route('client.quotes.show', $quote))
            ->assertOk()
            ->assertSee('QUO-CLIENT-0001')
            ->assertSee('Cloud Backup');
    }

    public function test_client_can_accept_sent_quote(): void
    {
        [$user, $client] = $this->makeClientUser();
        $quote = Quote::factory()->sent()->create(['client_id' => $client->id]);

        $this->actingAs($user)
            ->post(route('client.quotes.accept', $quote))
            ->assertRedirect(route('client.quotes.show', $quote))
            ->assertSessionHas('status');

        $this->assertSame(QuoteStatus::Accepted, $quote->fresh()->status);
    }

    public function test_client_can_decline_sent_quote(): void
    {
        [$user, $client] = $this->makeClientUser();
        $quote = Quote::factory()->sent()->create(['client_id' => $client->id]);

        $this->actingAs($user)
            ->post(route('client.quotes.decline', $quote))
            ->assertRedirect(route('client.quotes.show', $quote))
            ->assertSessionHas('status');

        $this->assertSame(QuoteStatus::Declined, $quote->fresh()->status);
    }

    public function test_client_cannot_view_another_clients_quote(): void
    {
        [$user] = $this->makeClientUser();
        $other = Client::factory()->create();
        $quote = Quote::factory()->sent()->create(['client_id' => $other->id]);

        $this->actingAs($user)
            ->get(route('client.quotes.show', $quote))
            ->assertNotFound();
    }

    public function test_client_can_view_own_payments_index(): void
    {
        [$user, $client] = $this->makeClientUser();
        $invoice = Invoice::factory()->unpaid()->create(['client_id' => $client->id]);
        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $client->id,
        ]);

        $this->actingAs($user)
            ->get(route('client.payments.index'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('client.payments.show', $payment))
            ->assertOk();
    }

    public function test_guest_is_redirected_from_client_billing(): void
    {
        $this->get(route('client.invoices.index'))
            ->assertRedirect(route('login'));
    }

    public function test_admin_without_client_access_cannot_view_client_billing(): void
    {
        $admin = User::factory()->withRole('admin')->create();

        $this->actingAs($admin)
            ->get(route('client.invoices.index'))
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $clientAttributes
     * @return array{0: User, 1: Client}
     */
    private function makeClientUser(array $clientAttributes = []): array
    {
        $user = User::factory()->withRole('client')->create();
        $client = Client::factory()->create([
            'user_id' => $user->id,
            ...$clientAttributes,
        ]);
        $client->users()->attach($user->id, [
            'role' => ClientMembershipRole::Owner->value,
        ]);

        return [$user, $client];
    }
}
