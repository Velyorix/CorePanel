<?php

namespace Tests\Feature\Billing;

use Core\Billing\Models\Invoice;
use Core\Billing\Models\InvoiceItem;
use Core\Billing\Models\Quote;
use Core\Billing\Models\QuoteItem;
use Core\Billing\Services\BillingDocumentPdfService;
use Core\Billing\Services\BillingSettings;
use Core\Products\Enums\BillingCycle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingDocumentPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private BillingDocumentPdfService $pdfs;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'corepanel.billing.seller.name' => 'Velyorix SAS',
            'corepanel.billing.seller.address' => '10 Rue du Test',
            'corepanel.billing.seller.city' => 'Paris',
            'corepanel.billing.seller.postal_code' => '75001',
            'corepanel.billing.seller.country' => 'FR',
            'corepanel.billing.seller.vat_number' => 'FR12345678901',
            'corepanel.billing.seller.footer' => 'Thank you for your business.',
        ]);

        $this->pdfs = app(BillingDocumentPdfService::class);
    }

    public function test_service_is_registered_as_singleton(): void
    {
        $this->assertSame(
            app(BillingDocumentPdfService::class),
            app(BillingDocumentPdfService::class),
        );
    }

    public function test_seller_profile_comes_from_billing_settings(): void
    {
        $seller = app(BillingSettings::class)->seller();

        $this->assertSame('Velyorix SAS', $seller['name']);
        $this->assertSame('10 Rue du Test', $seller['address']);
        $this->assertSame('FR12345678901', $seller['vat_number']);
    }

    public function test_invoice_html_contains_seller_buyer_and_totals(): void
    {
        $invoice = $this->makeInvoice();

        $html = $this->pdfs->invoiceHtml($invoice);

        $this->assertStringContainsString('Velyorix SAS', $html);
        $this->assertStringContainsString('INV-TEST-000001', $html);
        $this->assertStringContainsString('Acme Hosting', $html);
        $this->assertStringContainsString('Managed VPS', $html);
        $this->assertStringContainsString('100.00', $html);
        $this->assertStringContainsString('120.00', $html);
        $this->assertStringContainsString('Thank you for your business.', $html);
    }

    public function test_quote_html_contains_number_and_lines(): void
    {
        $quote = $this->makeQuote();

        $html = $this->pdfs->quoteHtml($quote);

        $this->assertStringContainsString('QUO-TEST-000001', $html);
        $this->assertStringContainsString('Alice Client', $html);
        $this->assertStringContainsString('Cloud Backup', $html);
        $this->assertStringContainsString('50.00', $html);
        $this->assertStringContainsString('60.00', $html);
    }

    public function test_render_invoice_returns_pdf_binary(): void
    {
        $invoice = $this->makeInvoice();

        $binary = $this->pdfs->renderInvoice($invoice);

        $this->assertNotSame('', $binary);
        $this->assertStringStartsWith('%PDF', $binary);
    }

    public function test_render_quote_returns_pdf_binary(): void
    {
        $quote = $this->makeQuote();

        $binary = $this->pdfs->renderQuote($quote);

        $this->assertNotSame('', $binary);
        $this->assertStringStartsWith('%PDF', $binary);
    }

    public function test_download_invoice_response_is_pdf_attachment(): void
    {
        $invoice = $this->makeInvoice();

        $response = $this->pdfs->downloadInvoice($invoice);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString(
            'INV-TEST-000001.pdf',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_filenames_sanitize_document_numbers(): void
    {
        $invoice = Invoice::factory()->unpaid()->create([
            'invoice_number' => 'INV/2026 001',
        ]);
        $quote = Quote::factory()->sent()->create([
            'quote_number' => 'QUO 2026/001',
        ]);

        $this->assertSame('INV-2026-001.pdf', $this->pdfs->invoiceFilename($invoice));
        $this->assertSame('QUO-2026-001.pdf', $this->pdfs->quoteFilename($quote));
    }

    private function makeInvoice(): Invoice
    {
        $invoice = Invoice::factory()->unpaid()->create([
            'invoice_number' => 'INV-TEST-000001',
            'company_name' => 'Acme Hosting',
            'contact_name' => 'Bob Buyer',
            'contact_email' => 'bob@example.test',
            'subtotal' => '100.00',
            'discount_amount' => '0.00',
            'tax_amount' => '20.00',
            'total_amount' => '120.00',
            'notes' => 'Net 14 days',
        ]);

        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Managed VPS',
            'product_name' => 'Managed VPS',
            'billing_cycle' => BillingCycle::Monthly,
            'quantity' => 1,
            'unit_price' => '100.00',
            'setup_fee' => '0.00',
            'tax_amount' => '20.00',
            'line_total' => '100.00',
        ]);

        return $invoice->fresh(['items']) ?? $invoice;
    }

    private function makeQuote(): Quote
    {
        $quote = Quote::factory()->sent()->create([
            'quote_number' => 'QUO-TEST-000001',
            'company_name' => null,
            'contact_name' => 'Alice Client',
            'contact_email' => 'alice@example.test',
            'subtotal' => '50.00',
            'tax_amount' => '10.00',
            'total_amount' => '60.00',
        ]);

        QuoteItem::factory()->create([
            'quote_id' => $quote->id,
            'description' => 'Cloud Backup',
            'product_name' => 'Cloud Backup',
            'billing_cycle' => BillingCycle::Monthly,
            'quantity' => 1,
            'unit_price' => '50.00',
            'setup_fee' => '0.00',
            'tax_amount' => '10.00',
            'line_total' => '50.00',
        ]);

        return $quote->fresh(['items']) ?? $quote;
    }
}
