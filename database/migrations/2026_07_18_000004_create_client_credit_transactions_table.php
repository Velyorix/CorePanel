<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Client prepaid credit wallet: ledger + cached balance on clients.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->decimal('credit_balance', 12, 2)->default(0)->after('status');
        });

        Schema::create('client_credit_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('type', 32);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('reference', 128)->nullable();
            $table->text('description')->nullable();
            $table->string('idempotency_key', 128)->nullable()->unique();
            $table->timestamps();

            $table->index(['client_id', 'created_at']);
            $table->index(['client_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('client_credit_transactions');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('credit_balance');
        });
    }
};
