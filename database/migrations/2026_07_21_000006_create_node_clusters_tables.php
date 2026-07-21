<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('node_clusters', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 32)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('sort_order');
        });

        Schema::create('node_cluster_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')
                ->constrained('nodes')
                ->cascadeOnDelete();
            $table->foreignId('node_cluster_id')
                ->constrained('node_clusters')
                ->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('weight')->default(100);
            $table->timestamps();

            $table->unique(['node_id', 'node_cluster_id']);
            $table->index(['node_cluster_id', 'sort_order']);
            $table->index(['node_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('node_cluster_members');
        Schema::dropIfExists('node_clusters');
    }
};
