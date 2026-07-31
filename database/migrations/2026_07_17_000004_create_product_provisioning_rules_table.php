<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Product provisioning rules
     * node_group_key is a stable allocation key until node_groups exist
     */
    public function up(): void
    {
        Schema::create('product_provisioning_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->boolean('auto_provision')->default(false);
            $table->boolean('send_welcome_email')->default(true);
            $table->string('welcome_email_template')->nullable();
            $table->string('node_group_key')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index('auto_provision');
            $table->index('node_group_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_provisioning_rules');
    }
};
