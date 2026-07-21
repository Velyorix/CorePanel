<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('installed_modules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->string('version', 32);
            $table->boolean('enabled')->default(false);
            $table->json('config')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('signature', 128)->nullable();
            $table->string('path')->nullable();
            $table->timestamp('installed_at')->nullable();
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->index('enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('installed_modules');
    }
};
