<?php

namespace Tests\Feature\Billing;

use Core\Billing\Enums\InvoiceStatus;
use Core\Billing\Enums\PaymentStatus;
use Core\Billing\Gateways\ManualTransferGateway;
use Core\Billing\Models\Invoice;
use Core\Billing\Services\GatewayManager;
use Core\Billing\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Billing\FakePaymentGateway;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private PaymentService $payments;

    private FakePaymentGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->gateway = (new FakePaymentGateway)->withWebhookSupport('valid-signature');
        $gateways = app(GatewayManager::class);
        $gateways->flush();
        $gateways->register($this->gateway);
        $gateways->enable('fake');
        $this->payments = app(PaymentService::class);
    }

    public function test_webhook_route_is_registered(): void
    {
        $this->assertSame(
            url('/api/webhooks/payments/fake'),
            route('webhooks.payments', ['gateway' => 'fake']),
        );
    }

    public function test_webhook_completes_pending_payment_when_signature_is_valid(): void
    {
        $invoice = $this->makeUnpaidInvoice('42.00');
        $payment = $this->payments->initiate($invoice, 'fake');

        $this->postJson(route('webhooks.payments', ['gateway' => 'fake']), [
            'payment_id' => $payment->id,
            'transaction_id' => 'wh-txn-1',
            'gateway_reference' => 'wh-ref-1',
        ], [
            'X-Signature' => 'valid-signature',
        ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'payment_id' => $payment->id,
                'status' => PaymentStatus::Completed->value,
            ]);

        $this->assertSame(1, $this->gateway->webhookCalls);
        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
        $this->assertSame('wh-txn-1', $payment->fresh()->transaction_id);
        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status);
    }

    public function test_webhook_rejects_invalid_signature(): void
    {
        $invoice = $this->makeUnpaidInvoice('10.00');
        $payment = $this->payments->initiate($invoice, 'fake');

        $this->postJson(route('webhooks.payments', ['gateway' => 'fake']), [
            'payment_id' => $payment->id,
        ], [
            'X-Signature' => 'wrong-signature',
        ])
            ->assertStatus(400)
            ->assertJsonFragment(['message' => 'Invalid webhook signature for payment gateway [fake].']);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_webhook_returns_not_found_for_unknown_gateway(): void
    {
        $this->postJson(route('webhooks.payments', ['gateway' => 'stripe']), [
            'payment_id' => 1,
        ])->assertNotFound();
    }

    public function test_webhook_returns_not_found_when_gateway_does_not_support_webhooks(): void
    {
        app(GatewayManager::class)->register(app(ManualTransferGateway::class));

        $this->postJson(route('webhooks.payments', ['gateway' => ManualTransferGateway::KEY]), [
            'payment_id' => 1,
        ])->assertNotFound();
    }

    public function test_webhook_still_works_when_gateway_is_disabled(): void
    {
        $invoice = $this->makeUnpaidInvoice('15.00');
        $payment = $this->payments->initiate($invoice, 'fake');

        app(GatewayManager::class)->disable('fake');

        $this->postJson(route('webhooks.payments', ['gateway' => 'fake']), [
            'payment_id' => $payment->id,
            'transaction_id' => 'after-disable',
        ], [
            'X-Signature' => 'valid-signature',
        ])
            ->assertOk()
            ->assertJsonPath('status', PaymentStatus::Completed->value);

        $this->assertSame(PaymentStatus::Completed, $payment->fresh()->status);
    }

    public function test_webhook_can_mark_payment_failed(): void
    {
        $this->gateway->webhookFails();

        $invoice = $this->makeUnpaidInvoice('20.00');
        $payment = $this->payments->initiate($invoice, 'fake');

        $this->postJson(route('webhooks.payments', ['gateway' => 'fake']), [
            'payment_id' => $payment->id,
        ], [
            'X-Signature' => 'valid-signature',
        ])
            ->assertOk()
            ->assertJsonPath('status', PaymentStatus::Failed->value);

        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(InvoiceStatus::Unpaid, $invoice->fresh()->status);
    }

    public function test_webhook_without_payment_id_acknowledges_without_updating(): void
    {
        $this->postJson(route('webhooks.payments', ['gateway' => 'fake']), [
            'event' => 'ping',
        ], [
            'X-Signature' => 'valid-signature',
        ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'payment_id' => null,
                'status' => null,
            ]);

        $this->assertSame(1, $this->gateway->webhookCalls);
    }

    public function test_webhook_rejects_mismatched_payment_method(): void
    {
        $other = (new FakePaymentGateway(key: 'other', label: 'Other'))->withWebhookSupport();
        $gateways = app(GatewayManager::class);
        $gateways->register($other);
        $gateways->enable('other');

        $invoice = $this->makeUnpaidInvoice('25.00');
        $payment = $this->payments->initiate($invoice, 'other');

        $this->postJson(route('webhooks.payments', ['gateway' => 'fake']), [
            'payment_id' => $payment->id,
        ], [
            'X-Signature' => 'valid-signature',
        ])
            ->assertStatus(422)
            ->assertJsonFragment([
                'message' => 'Webhook gateway key does not match the payment method.',
            ]);

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
    }

    private function makeUnpaidInvoice(string $total): Invoice
    {
        return Invoice::factory()->unpaid()->create([
            'subtotal' => $total,
            'tax_amount' => '0.00',
            'total_amount' => $total,
        ]);
    }
}
