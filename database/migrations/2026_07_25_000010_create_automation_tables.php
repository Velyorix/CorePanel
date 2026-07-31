<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('trigger_event')->index();
            $table->json('conditions')->nullable();
            $table->json('steps');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['trigger_event', 'is_active']);
            $table->index(['is_active', 'priority']);
        });

        Schema::create('automation_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('condition_json');
            $table->json('action_json');
            $table->unsignedInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'priority']);
        });

        Schema::create('automation_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workflow_id')->nullable()->constrained('workflows')->nullOnDelete();
            $table->foreignId('automation_rule_id')->nullable()->constrained('automation_rules')->nullOnDelete();
            $table->string('trigger_event')->index();
            $table->string('status', 32)->index();
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workflow_id', 'created_at']);
            $table->index(['automation_rule_id', 'created_at']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('workflows');
    }
};
