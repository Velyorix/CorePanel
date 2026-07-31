<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support custom billing intervals.
     */
    public function up(): void
    {
        Schema::table('product_pricing', function (Blueprint $table) {
            $table->unsignedInteger('custom_interval_days')->nullable()->after('billing_cycle');
        });

        Schema::table('product_addons', function (Blueprint $table) {
            $table->unsignedInteger('custom_interval_days')->nullable()->after('billing_cycle');
        });
    }

    public function down(): void
    {
        Schema::table('product_pricing', function (Blueprint $table) {
            $table->dropColumn('custom_interval_days');
        });

        Schema::table('product_addons', function (Blueprint $table) {
            $table->dropColumn('custom_interval_days');
        });
    }
};
