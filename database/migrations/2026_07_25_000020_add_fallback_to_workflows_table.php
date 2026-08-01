<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflows', function (Blueprint $table): void {
            $table->json('fallback')->nullable()->after('steps');
        });
    }

    public function down(): void
    {
        Schema::table('workflows', function (Blueprint $table): void {
            $table->dropColumn('fallback');
        });
    }
};
