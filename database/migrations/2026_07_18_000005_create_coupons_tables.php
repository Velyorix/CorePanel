<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('type', 32);
            $table->decimal('value', 12, 2);
            $table->string('currency', 3)->nullable();
            $table->string('applies_to', 32)->default('order');
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->unsignedInteger('max_uses_per_client')->default(1);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->restrictOnDelete();
            $table->json('product_ids')->nullable();
            $table->unsignedInteger('recurring_cycles')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'expires_at']);
            $table->index('client_id');
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamp('redeemed_at');
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['coupon_id', 'client_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('coupon_id')
                ->nullable()
                ->after('coupon_code')
                ->constrained('coupons')
                ->nullOnDelete();
            $table->decimal('discount_amount', 12, 2)
                ->default(0)
                ->after('subtotal_setup');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('coupon_id')
                ->nullable()
                ->after('order_id')
                ->constrained('coupons')
                ->nullOnDelete();
            $table->decimal('discount_amount', 12, 2)
                ->default(0)
                ->after('subtotal');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount_amount');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('discount_amount');
        });

        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupons');
    }
};
