<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Link products to server provider modules (roadmap 9.7).
     * `module` already exists; this adds required capability declarations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('module_capabilities')->nullable()->after('module');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('module_capabilities');
        });
    }
};
