<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Client order flow cart foundation (roadmap 10.1, CDC Tome 7 / Tome 1).
     * Guest carts are keyed by session_id; authenticated carts by client_id.
     * Merge session → client happens in CartService (10.2).
     */
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')
                ->nullable()
                ->constrained('clients')
                ->nullOnDelete();
            $table->string('session_id')->nullable();
            $table->string('status', 32)->default('open');
            $table->string('currency', 3)->nullable();
            $table->timestamps();

            $table->index('session_id');
            $table->index('status');
            $table->index(['client_id', 'status']);
            $table->index(['session_id', 'status']);
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('billing_cycle', 32);
            $table->unsignedInteger('custom_interval_days')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->json('options')->nullable();
            $table->json('addons')->nullable();
            $table->json('config_data')->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('setup_fee', 12, 2)->nullable()->default(0);
            $table->timestamps();

            $table->index('cart_id');
            $table->index('product_id');
            $table->index('billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
