<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Resource capacity limits for infrastructure nodes.
     */
    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->unsignedInteger('max_cpu_cores')->nullable()->after('max_services');
            $table->unsignedBigInteger('max_ram_mb')->nullable()->after('max_cpu_cores');
            $table->unsignedInteger('max_disk_gb')->nullable()->after('max_ram_mb');
        });
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['max_cpu_cores', 'max_ram_mb', 'max_disk_gb']);
        });
    }
};
