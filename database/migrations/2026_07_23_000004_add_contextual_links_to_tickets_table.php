<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional service, order, and invoice context on support tickets.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('service_id')
                ->nullable()
                ->after('category_id')
                ->constrained('services')
                ->nullOnDelete();
            $table->foreignId('order_id')
                ->nullable()
                ->after('service_id')
                ->constrained('orders')
                ->nullOnDelete();
            $table->foreignId('invoice_id')
                ->nullable()
                ->after('order_id')
                ->constrained('invoices')
                ->nullOnDelete();

            $table->index('service_id');
            $table->index('order_id');
            $table->index('invoice_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
            $table->dropConstrainedForeignId('order_id');
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
