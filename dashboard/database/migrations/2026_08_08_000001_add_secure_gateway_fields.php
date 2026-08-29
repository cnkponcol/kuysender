<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sessions', function (Blueprint $table) {
            $table->string('connection_state', 32)->default('disconnected')->after('status')->index();
            $table->longText('qr_code')->nullable()->after('connection_state');
            $table->timestamp('qr_expires_at')->nullable()->after('qr_code');
            $table->timestamp('last_seen_at')->nullable()->after('qr_expires_at');
            $table->text('last_error')->nullable()->after('last_seen_at');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('profile_name')->nullable()->after('name');
            $table->string('opt_in_status', 20)->default('unknown')->after('number')->index();
            $table->string('opt_in_source')->nullable()->after('opt_in_status');
            $table->timestamp('opted_in_at')->nullable()->after('opt_in_source');
            $table->timestamp('opted_out_at')->nullable()->after('opted_in_at');
            $table->json('tags')->nullable()->after('opted_out_at');
            $table->timestamp('first_chat_at')->nullable()->after('tags');
            $table->timestamp('last_chat_at')->nullable()->after('first_chat_at');
            $table->boolean('human_takeover')->default(false)->after('last_chat_at');
            $table->timestamp('ai_paused_until')->nullable()->after('human_takeover');
            $table->timestamp('blocklisted_at')->nullable()->after('ai_paused_until');
            $table->index(['session_id', 'number']);
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->unsignedInteger('delay_max')->nullable()->after('delay');
            $table->unsignedInteger('max_recipients')->nullable()->after('delay_max');
            $table->time('send_window_start')->nullable()->after('max_recipients');
            $table->time('send_window_end')->nullable()->after('send_window_start');
            $table->unsignedTinyInteger('stop_error_rate')->default(35)->after('send_window_end');
            $table->unsignedInteger('processed_count')->default(0)->after('stop_error_rate');
            $table->unsignedInteger('error_count')->default(0)->after('processed_count');
            $table->boolean('opt_in_only')->default(true)->after('error_count');
            $table->text('stopped_reason')->nullable()->after('opt_in_only');
        });

        Schema::table('bulks', function (Blueprint $table) {
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->timestamp('next_attempt_at')->nullable()->after('attempts')->index();
            $table->timestamp('sent_at')->nullable()->after('next_attempt_at');
            $table->text('error_message')->nullable()->after('sent_at');
            $table->unsignedBigInteger('contact_id')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('bulks', function (Blueprint $table) {
            $table->dropColumn(['attempts', 'next_attempt_at', 'sent_at', 'error_message', 'contact_id']);
        });
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['delay_max', 'max_recipients', 'send_window_start', 'send_window_end', 'stop_error_rate', 'processed_count', 'error_count', 'opt_in_only', 'stopped_reason']);
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['profile_name', 'opt_in_status', 'opt_in_source', 'opted_in_at', 'opted_out_at', 'tags', 'first_chat_at', 'last_chat_at', 'human_takeover', 'ai_paused_until', 'blocklisted_at']);
        });
        Schema::table('sessions', function (Blueprint $table) {
            $table->dropColumn(['connection_state', 'qr_code', 'qr_expires_at', 'last_seen_at', 'last_error']);
        });
    }
};
