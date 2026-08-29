<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_clients', function (Blueprint $table) {
            $table->longText('secret_value')->nullable()->after('secret_hash');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('wa_jid')->nullable()->after('number');
            $table->index(['session_id', 'wa_jid']);
        });

        Schema::table('ai_knowledge_items', function (Blueprint $table) {
            $table->string('category', 120)->nullable()->after('title')->index();
        });
    }

    public function down(): void
    {
        Schema::table('ai_knowledge_items', function (Blueprint $table) {
            $table->dropColumn('category');
        });
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropIndex(['session_id', 'wa_jid']);
            $table->dropColumn('wa_jid');
        });
        Schema::table('api_clients', function (Blueprint $table) {
            $table->dropColumn('secret_value');
        });
    }
};
