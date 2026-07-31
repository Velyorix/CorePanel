<?php

namespace Tests\Feature\Billing;

use App\Models\User;
use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Enums\QuoteStatus;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\Invoice;
use Core\Billing\Models\InvoiceItem;
use Core\Billing\Models\Payment;
use Core\Billing\Models\Quote;
use Core\Billing\Models\QuoteItem;
use Core\Billing\Services\PaymentService;
use Core\Clients\Models\Client;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBillingUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        config([
            'corepanel.rbac.cache.enabled' => true,
            'corepanel.rbac.cache.store' => 'array',
            'corepanel.rbac.cache.prefix' => 'test.rbac.admin-billing',
        ]);
    }

    public function test_admin_can_view_invoices_index_and_show(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $client = Client::factory()->create(['company_name' => 'Acme Hosting']);
        $invoice = Invoice::factory()->unpaid()->create([
            'client_id' => $client->id,
            'invoice_number' => 'INV-20260717-000042',
            'contact_email' => 'billing@acme.test',
            'total_amount' => '49.99',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'product_name' => 'Managed VPS',
            'line_total' => '49.99',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoices.index'))
            ->assertOk()
            ->assertSee('INV-20260717-000042')
            ->assertSee('Acme Hosting');

        $this->actingAs($admin)
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('INV-20260717-000042')
            ->assertSee('Managed VPS')
            ->assertSee('billing@acme.test');
    }

    public function test_admin_can_issue_draft_invoice(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $invoice = Invoice::factory()->draft()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $this->actingAs($admin)
            ->post(route('admin.invoices.issue', $invoice))
            ->assertRedirect(route('admin.invoices.show', $invoice))
            ->assertSessionHas('status');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertNotNull($invoice->issued_at);
    }

    public function test_admin_cannot_issue_draft_invoice_without_items(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $invoice = Invoice::factory()->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.invoices.issue', $invoice))
            ->assertRedirect()
            ->assertSessionHasErrors('invoice');

        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
    }

    public function test_admin_can_download_invoice_pdf(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $invoice = Invoice::factory()->unpaid()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);

        $response = $this->actingAs($admin)->get(route('admin.invoices.pdf', $invoice));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }

    public function test_admin_can_view_quotes_index_and_show(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $quote = Quote::factory()->sent()->create([
            'quote_number' => 'QUO-20260717-000007',
            'contact_email' => 'quotes@acme.test',
        ]);
        QuoteItem::factory()->create([
            'quote_id' => $quote->id,
            'product_name' => 'Cloud Backup',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.quotes.index'))
            ->assertOk()
            ->assertSee('QUO-20260717-000007');

        $this->actingAs($admin)
            ->get(route('admin.quotes.show', $quote))
            ->assertOk()
            ->assertSee('QUO-20260717-000007')
            ->assertSee('Cloud Backup');
    }

    public function test_admin_can_send_draft_quote(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $quote = Quote::factory()->draft()->create();
        QuoteItem::factory()->create(['quote_id' => $quote->id]);

        $this->actingAs($admin)
            ->post(route('admin.quotes.send', $quote))
            ->assertRedirect(route('admin.quotes.show', $quote))
            ->assertSessionHas('status');

        $quote->refresh();
        $this->assertSame(QuoteStatus::Sent, $quote->status);
        $this->assertNotNull($quote->quote_number);
    }

    public function test_admin_can_convert_sent_quote_to_invoice(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $quote = Quote::factory()->sent()->create();
        QuoteItem::factory()->create(['quote_id' => $quote->id, 'line_total' => '75.00']);
        $quote->update(['total_amount' => '75.00', 'subtotal' => '75.00']);

        $response = $this->actingAs($admin)->post(route('admin.quotes.convert', $quote));

        $quote->refresh();
        $this->assertSame(QuoteStatus::Converted, $quote->status);
        $this->assertNotNull($quote->converted_invoice_id);

        $response->assertRedirect(route('admin.invoices.show', $quote->converted_invoice_id));
        $response->assertSessionHas('status');

        $invoice = Invoice::query()->findOrFail($quote->converted_invoice_id);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
    }

    public function test_admin_can_cancel_open_quote(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $quote = Quote::factory()->sent()->create();

        $this->actingAs($admin)
            ->post(route('admin.quotes.cancel', $quote))
            ->assertRedirect(route('admin.quotes.show', $quote))
            ->assertSessionHas('status');

        $this->assertSame(QuoteStatus::Cancelled, $quote->fresh()->status);
    }

    public function test_admin_can_view_payments_index_and_show(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $invoice = Invoice::factory()->unpaid()->create();
        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
            'method' => ManualTransferGateway::KEY,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.payments.index'))
            ->assertOk()
            ->assertSee('#'.$payment->id);

        $this->actingAs($admin)
            ->get(route('admin.payments.show', $payment))
            ->assertOk();
    }

    public function test_admin_can_complete_pending_payment(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $invoice = Invoice::factory()->unpaid()->create(['total_amount' => '50.00']);
        $payment = app(PaymentService::class)->initiate($invoice, ManualTransferGateway::KEY, '50.00');

        $this->actingAs($admin)
            ->post(route('admin.payments.complete', $payment))
            ->assertRedirect()
            ->assertSessionHas('status');

        $payment->refresh();
        $this->assertSame(PaymentStatus::Completed, $payment->status);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_admin_can_fail_pending_payment(): void
    {
        $admin = User::factory()->withRole('admin')->create();
        $invoice = Invoice::factory()->unpaid()->create(['total_amount' => '50.00']);
        $payment = app(PaymentService::class)->initiate($invoice, ManualTransferGateway::KEY, '50.00');

        $this->actingAs($admin)
            ->post(route('admin.payments.fail', $payment))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
    }

    public function test_support_cannot_manage_billing_actions(): void
    {
        $support = User::factory()->withRole('support')->create();
        $invoice = Invoice::factory()->draft()->create();
        InvoiceItem::factory()->create(['invoice_id' => $invoice->id]);
        $quote = Quote::factory()->draft()->create();

        $this->actingAs($support)
            ->get(route('admin.invoices.index'))
            ->assertOk();

        $this->actingAs($support)
            ->post(route('admin.invoices.issue', $invoice))
            ->assertForbidden();

        $this->actingAs($support)
            ->post(route('admin.quotes.send', $quote))
            ->assertForbidden();

        $payment = Payment::factory()->pending()->create([
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->client_id,
        ]);

        $this->actingAs($support)
            ->post(route('admin.payments.complete', $payment))
            ->assertForbidden();
    }

    public function test_client_role_cannot_access_admin_billing(): void
    {
        $clientUser = User::factory()->withRole('client')->create();

        $this->actingAs($clientUser)
            ->get(route('admin.invoices.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_admin_billing(): void
    {
        $this->get(route('admin.invoices.index'))
            ->assertRedirect(route('login'));
    }
}
