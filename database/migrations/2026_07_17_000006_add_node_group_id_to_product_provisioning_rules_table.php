<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Formal product → node group assignment.
     */
    public function up(): void
    {
        Schema::table('product_provisioning_rules', function (Blueprint $table) {
            $table->foreignId('node_group_id')
                ->nullable()
                ->after('node_group_key')
                ->constrained('node_groups')
                ->nullOnDelete();

            $table->index('node_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('product_provisioning_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('node_group_id');
        });
    }
};
