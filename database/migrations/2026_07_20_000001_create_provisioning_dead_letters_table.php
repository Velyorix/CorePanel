<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist definitive provisioning failures for later admin review / requeue.
     */
    public function up(): void
    {
        Schema::create('provisioning_dead_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('module')->nullable();
            $table->string('status', 32)->default('pending_review');
            $table->string('exception_class')->nullable();
            $table->text('exception_message')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->json('payload')->nullable();
            $table->timestamp('failed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index(['service_id', 'status']);
            $table->index('failed_at');
            $table->index('module');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provisioning_dead_letters');
    }
};
