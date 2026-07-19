<?php

namespace Tests\Feature\Billing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Schema smoke for billing + quotes migrations.
 */
class BillingSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_tables_exist(): void
    {
        foreach ([
            'invoices',
            'invoice_items',
            'payments',
            'credit_notes',
            'quotes',
            'quote_items',
            'billing_sequences',
            'tax_rules',
            'client_credit_transactions',
            'coupons',
            'coupon_redemptions',
        ] as $table) {
            $this->assertTrue(
                Schema::hasTable($table),
                "Expected table [{$table}] to exist.",
            );
        }
    }

    public function test_invoices_have_expected_columns(): void
    {
        foreach ([
            'id',
            'invoice_number',
            'client_id',
            'order_id',
            'created_by',
            'status',
            'currency',
            'contact_name',
            'contact_email',
            'company_name',
            'vat_number',
            'address',
            'city',
            'country',
            'postal_code',
            'phone',
            'notes',
            'subtotal',
            'tax_amount',
            'total_amount',
            'issued_at',
            'due_at',
            'reminder_level',
            'last_reminder_at',
            'reminders_sent',
            'overdue_action',
            'overdue_action_at',
            'paid_at',
            'cancelled_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('invoices', $column),
                "Expected invoices.{$column} to exist.",
            );
        }
    }

    public function test_invoice_items_have_expected_columns(): void
    {
        foreach ([
            'id',
            'invoice_id',
            'product_id',
            'service_id',
            'description',
            'product_name',
            'product_slug',
            'billing_cycle',
            'custom_interval_days',
            'quantity',
            'options',
            'addons',
            'config_data',
            'unit_price',
            'setup_fee',
            'tax_amount',
            'line_total',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('invoice_items', $column),
                "Expected invoice_items.{$column} to exist.",
            );
        }
    }

    public function test_payments_have_expected_columns(): void
    {
        foreach ([
            'id',
            'invoice_id',
            'client_id',
            'method',
            'currency',
            'amount',
            'status',
            'transaction_id',
            'gateway_reference',
            'notes',
            'paid_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('payments', $column),
                "Expected payments.{$column} to exist.",
            );
        }
    }

    public function test_credit_notes_have_expected_columns(): void
    {
        foreach ([
            'id',
            'credit_note_number',
            'invoice_id',
            'client_id',
            'created_by',
            'currency',
            'amount',
            'status',
            'settlement',
            'payment_id',
            'reason',
            'notes',
            'issued_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('credit_notes', $column),
                "Expected credit_notes.{$column} to exist.",
            );
        }
    }

    public function test_quotes_and_quote_items_have_expected_columns(): void
    {
        foreach ([
            'id',
            'quote_number',
            'client_id',
            'created_by',
            'converted_invoice_id',
            'status',
            'currency',
            'contact_name',
            'contact_email',
            'company_name',
            'vat_number',
            'address',
            'city',
            'country',
            'postal_code',
            'phone',
            'notes',
            'subtotal',
            'tax_amount',
            'total_amount',
            'valid_until',
            'sent_at',
            'accepted_at',
            'converted_at',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('quotes', $column),
                "Expected quotes.{$column} to exist.",
            );
        }

        foreach ([
            'id',
            'quote_id',
            'product_id',
            'description',
            'product_name',
            'product_slug',
            'billing_cycle',
            'custom_interval_days',
            'quantity',
            'options',
            'addons',
            'config_data',
            'unit_price',
            'setup_fee',
            'tax_amount',
            'line_total',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('quote_items', $column),
                "Expected quote_items.{$column} to exist.",
            );
        }
    }

    public function test_billing_sequences_have_expected_columns(): void
    {
        foreach (['id', 'name', 'current_value', 'year', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('billing_sequences', $column),
                "Expected billing_sequences.{$column} to exist.",
            );
        }
    }

    public function test_tax_rules_have_expected_columns(): void
    {
        foreach (['id', 'country', 'rate', 'type', 'active', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn('tax_rules', $column),
                "Expected tax_rules.{$column} to exist.",
            );
        }
    }

    public function test_client_credit_transactions_have_expected_columns(): void
    {
        foreach ([
            'id',
            'client_id',
            'created_by',
            'type',
            'amount',
            'balance_after',
            'currency',
            'reference',
            'description',
            'idempotency_key',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('client_credit_transactions', $column),
                "Expected client_credit_transactions.{$column} to exist.",
            );
        }

        $this->assertTrue(
            Schema::hasColumn('clients', 'credit_balance'),
            'Expected clients.credit_balance to exist.',
        );
    }

    public function test_coupons_have_expected_columns(): void
    {
        foreach ([
            'id',
            'code',
            'type',
            'value',
            'currency',
            'applies_to',
            'max_uses',
            'uses_count',
            'max_uses_per_client',
            'starts_at',
            'expires_at',
            'client_id',
            'product_ids',
            'recurring_cycles',
            'active',
            'created_at',
            'updated_at',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('coupons', $column),
                "Expected coupons.{$column} to exist.",
            );
        }

        foreach (['coupon_id', 'discount_amount'] as $column) {
            $this->assertTrue(Schema::hasColumn('orders', $column));
            $this->assertTrue(Schema::hasColumn('invoices', $column));
        }
    }
}
