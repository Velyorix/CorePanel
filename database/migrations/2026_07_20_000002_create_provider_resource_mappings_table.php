<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bidirectional mapping between CorePanel services and provider resources.
     */
    public function up(): void
    {
        Schema::create('provider_resource_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('module');
            $table->string('external_id');
            $table->string('resource_type', 64)->default('server');
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['service_id', 'resource_type'], 'prm_service_resource_type_unique');
            $table->unique(['module', 'external_id', 'resource_type'], 'prm_module_external_resource_unique');
            $table->index('module');
            $table->index('external_id');
            $table->index('resource_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_resource_mappings');
    }
};
