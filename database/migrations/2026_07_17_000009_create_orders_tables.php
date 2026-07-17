<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Minimal orders schema for cart → order conversion.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('cart_id')
                ->nullable()
                ->constrained('carts')
                ->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $table->string('currency', 3)->nullable();
            $table->string('payment_method', 64)->nullable();
            $table->string('coupon_code', 64)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('company_name')->nullable();
            $table->string('vat_number', 64)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('phone', 64)->nullable();
            $table->decimal('subtotal_recurring', 12, 2)->default(0);
            $table->decimal('subtotal_setup', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->timestamp('placed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['client_id', 'status']);
            $table->unique('cart_id');
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('billing_cycle', 32);
            $table->unsignedInteger('custom_interval_days')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('options')->nullable();
            $table->json('addons')->nullable();
            $table->json('config_data')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('setup_fee', 12, 2)->nullable()->default(0);
            $table->decimal('line_total', 12, 2)->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index('product_id');
            $table->index('billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
