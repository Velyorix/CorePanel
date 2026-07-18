<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Billing + quotes schema.
     *
     * `quote_items` mirrors `invoice_items` for devis → facture conversion.
     * `service_id` on line items is an unsigned FK placeholder.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number', 64)->nullable()->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('currency', 3)->default('EUR');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('vat_number', 64)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('phone', 64)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['client_id', 'status']);
            $table->index('due_at');
            $table->index('order_id');
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->unsignedBigInteger('service_id')->nullable()->index();
            $table->string('description');
            $table->string('product_name')->nullable();
            $table->string('product_slug')->nullable();
            $table->string('billing_cycle', 32)->nullable();
            $table->unsignedInteger('custom_interval_days')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('options')->nullable();
            $table->json('addons')->nullable();
            $table->json('config_data')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();

            $table->index('invoice_id');
            $table->index('product_id');
            $table->index('billing_cycle');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('method', 64);
            $table->string('currency', 3)->default('EUR');
            $table->decimal('amount', 12, 2);
            $table->string('status', 32)->default('pending');
            $table->string('transaction_id', 128)->nullable();
            $table->string('gateway_reference', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['client_id', 'status']);
            $table->index('invoice_id');
            $table->index('method');
            $table->index('transaction_id');
        });

        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->string('credit_note_number', 64)->nullable()->unique();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('amount', 12, 2);
            $table->string('status', 32)->default('draft');
            $table->text('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['client_id', 'status']);
            $table->index('invoice_id');
        });

        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            $table->string('quote_number', 64)->nullable()->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('converted_invoice_id')
                ->nullable()
                ->constrained('invoices')
                ->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('currency', 3)->default('EUR');
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('vat_number', 64)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('phone', 64)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('valid_until')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['client_id', 'status']);
            $table->index('valid_until');
            $table->index('converted_invoice_id');
        });

        Schema::create('quote_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('product_id')
                ->nullable()
                ->constrained('products')
                ->nullOnDelete();
            $table->string('description');
            $table->string('product_name')->nullable();
            $table->string('product_slug')->nullable();
            $table->string('billing_cycle', 32)->nullable();
            $table->unsignedInteger('custom_interval_days')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('options')->nullable();
            $table->json('addons')->nullable();
            $table->json('config_data')->nullable();
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2)->default(0);
            $table->timestamps();

            $table->index('quote_id');
            $table->index('product_id');
            $table->index('billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_items');
        Schema::dropIfExists('quotes');
        Schema::dropIfExists('credit_notes');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
