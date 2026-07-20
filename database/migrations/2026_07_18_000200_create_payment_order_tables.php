<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_no', 40)->unique();
            $table->foreignId('application_id')->nullable()->constrained('developer_applications')->restrictOnDelete();
            $table->string('external_order_no', 80)->nullable();
            $table->string('source', 32);
            $table->string('status', 32)->default('creating');
            $table->unsignedInteger('version')->default(1);
            $table->unsignedBigInteger('expected_amount_cents');
            $table->unsignedBigInteger('paid_amount_cents')->nullable();
            $table->bigInteger('amount_difference_cents')->nullable();
            $table->char('currency', 3)->default('CNY');
            $table->string('subject', 160);
            $table->string('description', 500)->nullable();
            $table->string('payment_method', 24)->nullable();
            $table->string('gateway', 32)->default('easypay');
            $table->string('gateway_api_version', 8)->default('v2');
            $table->string('gateway_order_no', 96)->nullable();
            $table->string('gateway_trade_no', 96)->nullable();
            $table->char('checkout_token_hash', 64)->unique();
            $table->text('checkout_token_ciphertext');
            $table->string('payment_action_type', 24)->nullable();
            $table->text('payment_action_payload')->nullable();
            $table->text('payment_direct_url')->nullable();
            $table->text('notify_url')->nullable();
            $table->text('notify_secret_ciphertext')->nullable();
            $table->text('return_url')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->unsignedSmallInteger('gateway_create_attempt_count')->default(0);
            $table->timestamp('gateway_create_last_attempt_at')->nullable();
            $table->text('gateway_last_error')->nullable();
            $table->longText('metadata')->nullable();
            $table->dateTime('status_changed_at');
            $table->dateTime('expires_at');
            $table->dateTime('checkout_token_expires_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_reconciled_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'external_order_no'], 'pay_orders_app_external_uq');
            $table->unique(['gateway', 'gateway_order_no'], 'pay_orders_gateway_order_uq');
            $table->unique(['gateway', 'gateway_trade_no'], 'pay_orders_gateway_trade_uq');
            $table->index(['status', 'created_at'], 'pay_orders_status_created_idx');
            $table->index(['source', 'status'], 'pay_orders_source_status_idx');
            $table->index(['status', 'expires_at'], 'pay_orders_status_expires_idx');
            $table->index(['application_id', 'status', 'created_at'], 'pay_orders_app_status_created_idx');
        });

        Schema::create('payment_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('scope_key', 96);
            $table->char('idempotency_key_hash', 64);
            $table->char('request_fingerprint', 64);
            $table->foreignId('order_id')->constrained('payment_orders')->restrictOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['scope_key', 'idempotency_key_hash'], 'pay_idempotency_scope_key_uq');
            $table->index('expires_at', 'pay_idempotency_expires_idx');
        });

        Schema::create('payment_order_status_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('payment_orders')->restrictOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('source', 48);
            $table->longText('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at'], 'pay_status_events_order_created_idx');
            $table->index(['to_status', 'created_at'], 'pay_status_events_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_order_status_events');
        Schema::dropIfExists('payment_idempotency_keys');
        Schema::dropIfExists('payment_orders');
    }
};
