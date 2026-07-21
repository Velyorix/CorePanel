<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Infrastructure nodes used for provisioning assignment.
     */
    public function up(): void
    {
        Schema::create('nodes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32)->default('general');
            $table->string('module')->nullable();
            $table->string('hostname');
            $table->string('ip_address', 45)->nullable();
            $table->string('api_url')->nullable();
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('max_services')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('node_group_id')
                ->nullable()
                ->constrained('node_groups')
                ->nullOnDelete();
            $table->json('credentials')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('module');
            $table->index('status');
            $table->index('hostname');
            $table->index('sort_order');
            $table->index(['node_group_id', 'status']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->foreign('node_id')
                ->references('id')
                ->on('nodes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropForeign(['node_id']);
        });

        Schema::dropIfExists('nodes');
    }
};
