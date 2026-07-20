<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('developer_applications', function (Blueprint $table): void {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('status', 24)->default('active');
            $table->text('allowed_notify_urls')->nullable();
            $table->text('allowed_return_urls')->nullable();
            $table->text('ip_allowlist')->nullable();
            $table->unsignedBigInteger('minimum_amount_cents')->nullable();
            $table->unsignedBigInteger('maximum_amount_cents')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'dev_apps_user_status_idx');
        });

        Schema::create('developer_api_credentials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('developer_applications')->restrictOnDelete();
            $table->string('key_id', 64)->unique();
            $table->text('secret_ciphertext');
            $table->char('secret_fingerprint', 64);
            $table->char('secret_last_four', 4);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'revoked_at'], 'dev_credentials_app_revoked_idx');
        });

        Schema::create('api_request_nonces', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('developer_applications')->cascadeOnDelete();
            $table->char('nonce_hash', 64);
            $table->dateTime('request_timestamp');
            $table->dateTime('expires_at');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['application_id', 'nonce_hash'], 'api_nonces_app_hash_uq');
            $table->index('expires_at', 'api_nonces_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_nonces');
        Schema::dropIfExists('developer_api_credentials');
        Schema::dropIfExists('developer_applications');
    }
};
