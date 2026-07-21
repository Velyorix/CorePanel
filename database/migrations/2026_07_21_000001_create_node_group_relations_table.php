<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Many-to-many assignments between infrastructure nodes and node groups.
     */
    public function up(): void
    {
        Schema::create('node_group_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')
                ->constrained('nodes')
                ->cascadeOnDelete();
            $table->foreignId('node_group_id')
                ->constrained('node_groups')
                ->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['node_id', 'node_group_id']);
            $table->index(['node_group_id', 'sort_order']);
            $table->index(['node_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_group_relations');
    }
};
