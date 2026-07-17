<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Completes the orders schema for étape 11 (roadmap 11.1).
     * Base tables were introduced in 10.8 (`2026_07_17_000009_create_orders_tables`).
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_number', 64)
                ->nullable()
                ->unique()
                ->after('id');
            $table->foreignId('created_by')
                ->nullable()
                ->after('client_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->string('source', 32)
                ->default('client_checkout')
                ->after('created_by');
            $table->text('notes')
                ->nullable()
                ->after('phone');
            $table->timestamp('paid_at')
                ->nullable()
                ->after('placed_at');
            $table->timestamp('cancelled_at')
                ->nullable()
                ->after('paid_at');

            $table->index('source');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->string('product_name')
                ->nullable()
                ->after('product_id');
            $table->string('product_slug')
                ->nullable()
                ->after('product_name');

            $table->index('product_slug');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['product_slug']);
            $table->dropColumn(['product_name', 'product_slug']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropConstrainedForeignId('created_by');
            $table->dropUnique(['order_number']);
            $table->dropColumn([
                'order_number',
                'source',
                'notes',
                'paid_at',
                'cancelled_at',
            ]);
        });
    }
};
