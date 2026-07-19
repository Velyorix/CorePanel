<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Country VAT rates used by TaxCalculationService.
     */
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table) {
            $table->id();
            $table->string('country', 2)->nullable();
            $table->decimal('rate', 6, 4);
            $table->string('type', 32);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['country', 'type']);
            $table->index(['active', 'country']);
        });

        $now = now();

        DB::table('tax_rules')->insert([
            [
                'country' => 'FR',
                'rate' => '0.2000',
                'type' => 'standard',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'country' => null,
                'rate' => '0.2000',
                'type' => 'standard',
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
