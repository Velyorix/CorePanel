<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_health_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('nodes')->cascadeOnDelete();
            $table->string('state', 32);
            $table->unsignedInteger('latency_ms')->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('checked_at');
            $table->timestamp('created_at')->nullable();

            $table->index('node_id');
            $table->index('checked_at');
            $table->index(['node_id', 'checked_at']);
            $table->index('state');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_health_checks');
    }
};
