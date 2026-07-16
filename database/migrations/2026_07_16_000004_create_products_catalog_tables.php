<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Product catalog foundation (CDC Tome 3 §7, roadmap 9.1).
     * Pricing lives on product_pricing (one row per billing cycle).
     * Options, addons, provisioning rules, and node groups come in later 9.x steps.
     */
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('product_categories')
                ->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 32)->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('sort_order');
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('product_categories')
                ->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('type', 32)->default('other');
            $table->string('module')->nullable();
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('status');
            $table->index('type');
            $table->index('module');
            $table->index('sort_order');
        });

        Schema::create('product_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('billing_cycle', 32);
            $table->decimal('price', 12, 2);
            $table->decimal('setup_fee', 12, 2)->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['product_id', 'billing_cycle']);
            $table->index('billing_cycle');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_pricing');
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
