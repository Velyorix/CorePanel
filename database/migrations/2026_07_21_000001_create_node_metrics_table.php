<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->unsignedInteger('current_services')->nullable();
            $table->unsignedInteger('max_services')->nullable();
            $table->decimal('cpu_usage', 10, 2)->nullable();
            $table->decimal('ram_usage', 12, 2)->nullable();
            $table->decimal('disk_usage', 12, 2)->nullable();
            $table->decimal('network_in', 14, 2)->nullable();
            $table->decimal('network_out', 14, 2)->nullable();
            $table->decimal('load_average', 8, 2)->nullable();
            $table->boolean('capacity_available')->nullable();
            $table->string('status', 32)->default('success');
            $table->json('payload')->nullable();
            $table->timestamp('collected_at');
            $table->timestamp('created_at')->nullable();

            $table->index('node_id');
            $table->index('collected_at');
            $table->index(['node_id', 'collected_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_metrics');
    }
};
