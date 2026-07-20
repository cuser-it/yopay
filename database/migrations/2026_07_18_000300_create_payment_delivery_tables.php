<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_callbacks', function (Blueprint $table): void {
            $table->id();
            $table->ulid('request_id')->unique();
            $table->foreignId('order_id')->nullable()->constrained('payment_orders')->restrictOnDelete();
            $table->string('gateway', 32)->default('easypay');
            $table->string('gateway_api_version', 8);
            $table->char('fingerprint', 64)->unique();
            $table->string('gateway_trade_no', 96)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->boolean('merchant_valid')->default(false);
            $table->string('processing_status', 24)->default('received');
            $table->string('outcome', 48)->nullable();
            $table->string('error_code', 64)->nullable();
            $table->longText('sanitized_payload');
            $table->dateTime('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'received_at'], 'pay_callbacks_order_received_idx');
            $table->index(['processing_status', 'received_at'], 'pay_callbacks_status_received_idx');
        });

        Schema::create('payment_events', function (Blueprint $table): void {
            $table->id();
            $table->ulid('event_id')->unique();
            $table->foreignId('order_id')->constrained('payment_orders')->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('developer_applications')->restrictOnDelete();
            $table->string('event_type', 64);
            $table->longText('payload');
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at'], 'payment_events_order_created_idx');
            $table->index(['application_id', 'created_at'], 'payment_events_app_created_idx');
        });

        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('developer_applications')->restrictOnDelete();
            $table->string('name', 120);
            $table->text('url');
            $table->char('url_hash', 64);
            $table->text('secret_ciphertext');
            $table->text('subscribed_events');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamps();

            $table->unique(['application_id', 'url_hash'], 'webhook_endpoints_app_url_uq');
            $table->index(['application_id', 'enabled'], 'webhook_endpoints_app_enabled_idx');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_event_id')->constrained('payment_events')->restrictOnDelete();
            $table->foreignId('webhook_endpoint_id')->nullable()->constrained('webhook_endpoints')->nullOnDelete();
            $table->char('destination_hash', 64);
            $table->text('destination_url');
            $table->text('secret_ciphertext');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('response_summary', 500)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['payment_event_id', 'destination_hash'], 'webhook_delivery_event_target_uq');
            $table->index(['status', 'next_attempt_at'], 'webhook_delivery_status_next_idx');
        });

        Schema::create('webhook_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_delivery_id')->constrained('webhook_deliveries')->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_no');
            $table->dateTime('request_timestamp');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('response_summary', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error')->nullable();
            $table->dateTime('attempted_at');

            $table->unique(['webhook_delivery_id', 'attempt_no'], 'webhook_attempt_delivery_no_uq');
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('payment_orders')->restrictOnDelete();
            $table->foreignId('payment_event_id')->nullable()->constrained('payment_events')->restrictOnDelete();
            $table->string('channel', 32);
            $table->char('destination_hash', 64);
            $table->text('destination_ciphertext');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->timestamp('next_attempt_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('response_summary', 500)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['payment_event_id', 'channel', 'destination_hash'], 'notification_delivery_event_target_uq');
            $table->index(['status', 'next_attempt_at'], 'notification_delivery_status_next_idx');
            $table->index(['order_id', 'created_at'], 'notification_delivery_order_created_idx');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('application_id')->nullable()->constrained('developer_applications')->restrictOnDelete();
            $table->ulid('request_id')->nullable();
            $table->string('action', 80);
            $table->string('subject_type', 120)->nullable();
            $table->string('subject_id', 96)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['action', 'created_at'], 'audit_logs_action_created_idx');
            $table->index(['application_id', 'created_at'], 'audit_logs_app_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('webhook_delivery_attempts');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_endpoints');
        Schema::dropIfExists('payment_events');
        Schema::dropIfExists('payment_callbacks');
    }
};
