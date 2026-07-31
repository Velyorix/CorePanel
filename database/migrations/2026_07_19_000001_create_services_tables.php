<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Provisioned client services and related action / config storage.
     *
     * `node_id` is an unsigned FK placeholder until the nodes schema exists.
     * `service_config.data` stores ciphertext once encryption is enabled at the model layer.
     */
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('order_id')
                ->nullable()
                ->constrained('orders')
                ->nullOnDelete();
            $table->foreignId('order_item_id')
                ->nullable()
                ->constrained('order_items')
                ->nullOnDelete();
            $table->string('status', 32)->default('pending');
            $table->string('module')->nullable();
            $table->string('external_id')->nullable();
            $table->string('billing_cycle', 32)->nullable();
            $table->unsignedInteger('custom_interval_days')->nullable();
            $table->json('config_data')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('hostname')->nullable();
            $table->unsignedBigInteger('node_id')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('renewal_date')->nullable();
            $table->timestamp('next_billing_date')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('terminated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index(['client_id', 'status']);
            $table->index('product_id');
            $table->index('order_id');
            $table->index('order_item_id');
            $table->index('module');
            $table->index('external_id');
            $table->index('billing_cycle');
            $table->index('next_billing_date');
            $table->index('renewal_date');
        });

        Schema::create('service_actions_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('action');
            $table->string('status', 32)->default('pending');
            $table->json('response')->nullable();
            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index('service_id');
            $table->index('action');
            $table->index('status');
            $table->index(['service_id', 'created_at']);
        });

        Schema::create('service_config', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->text('data')->nullable();
            $table->timestamps();

            $table->unique('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_config');
        Schema::dropIfExists('service_actions_log');
        Schema::dropIfExists('services');
    }
};
