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
            $table->unsignedTinyInteger('reminder_level')
                ->default(0)
                ->after('due_at');
            $table->timestamp('last_reminder_at')
                ->nullable()
                ->after('reminder_level');
            $table->unsignedTinyInteger('reminders_sent')
                ->default(0)
                ->after('last_reminder_at');

            $table->index(['status', 'due_at', 'reminder_level'], 'invoices_reminder_lookup_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropIndex('invoices_reminder_lookup_index');
            $table->dropColumn(['reminder_level', 'last_reminder_at', 'reminders_sent']);
        });
    }
};
