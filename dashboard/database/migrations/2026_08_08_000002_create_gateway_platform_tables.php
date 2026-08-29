<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('session_id')->index();
            $table->string('wa_message_id')->nullable()->index();
            $table->string('chat_jid')->index();
            $table->string('sender_jid')->nullable()->index();
            $table->string('sender_name')->nullable();
            $table->string('direction', 10)->index();
            $table->string('message_type', 32)->default('text');
            $table->longText('body')->nullable();
            $table->text('media_url')->nullable();
            $table->string('media_mime')->nullable();
            $table->string('status', 32)->default('received')->index();
            $table->boolean('is_read')->default(false)->index();
            $table->longText('ai_suggestion')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('message_at')->nullable()->index();
            $table->timestamps();
            $table->index(['session_id', 'chat_jid', 'message_at']);
        });

        Schema::create('api_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('name');
            $table->string('key_id', 24)->unique();
            $table->string('secret_hash', 64);
            $table->json('scopes')->nullable();
            $table->unsignedInteger('rate_limit')->default(60);
            $table->text('webhook_url')->nullable();
            $table->longText('webhook_secret')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_client_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('api_client_id');
            $table->uuid('session_id');
            $table->timestamps();
            $table->unique(['api_client_id', 'session_id']);
            $table->index('session_id');
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('api_client_id')->index();
            $table->uuid('session_id')->nullable()->index();
            $table->string('event_type')->index();
            $table->json('payload');
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('session_id')->unique();
            $table->string('mode', 32)->default('off');
            $table->text('provider_url')->nullable();
            $table->longText('api_key')->nullable();
            $table->string('model')->nullable();
            $table->longText('system_prompt')->nullable();
            $table->json('business_hours')->nullable();
            $table->unsignedTinyInteger('max_context_messages')->default(12);
            $table->boolean('enabled')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('ai_knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('session_id')->index();
            $table->string('title');
            $table->longText('content');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('gateway_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->uuid('session_id')->nullable()->index();
            $table->uuid('api_client_id')->nullable()->index();
            $table->string('level', 16)->default('info')->index();
            $table->string('category', 32)->index();
            $table->text('message');
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('api_client_id')->nullable()->index();
            $table->string('method', 10);
            $table->string('path');
            $table->unsignedSmallInteger('status_code')->index();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('api_request_logs');
        Schema::dropIfExists('gateway_logs');
        Schema::dropIfExists('ai_knowledge_items');
        Schema::dropIfExists('ai_settings');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('api_client_sessions');
        Schema::dropIfExists('api_clients');
        Schema::dropIfExists('messages');
    }
};
