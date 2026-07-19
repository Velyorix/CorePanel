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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('overdue_action', 32)
                ->nullable()
                ->after('reminders_sent');
            $table->timestamp('overdue_action_at')
                ->nullable()
                ->after('overdue_action');

            $table->index(['status', 'due_at', 'overdue_action'], 'invoices_overdue_action_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_overdue_action_lookup_index');
            $table->dropColumn(['overdue_action', 'overdue_action_at']);
        });
    }
};
