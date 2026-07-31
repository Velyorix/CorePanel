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
     * Atomic sequence counters for billing document numbers.
     */
    public function up(): void
    {
        Schema::create('billing_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64)->unique();
            $table->unsignedBigInteger('current_value')->default(0);
            $table->unsignedSmallInteger('year')->nullable();
            $table->timestamps();
        });

        DB::table('billing_sequences')->insert([
            'name' => 'invoice',
            'current_value' => 0,
            'year' => (int) now()->format('Y'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_sequences');
    }
};
