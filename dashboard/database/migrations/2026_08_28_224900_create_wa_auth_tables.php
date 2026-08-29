<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wa_auth_sessions', function (Blueprint $table) {
            $table->uuid('session_id')->primary();
            $table->longText('payload');
            $table->string('cipher', 32)->default('aes-256-gcm');
            $table->unsignedTinyInteger('format_version')->default(1);
            $table->timestamps();
            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
        });

        Schema::create('wa_auth_keys', function (Blueprint $table) {
            $table->uuid('session_id');
            $table->string('key_type', 64);
            $table->string('key_id', 191);
            $table->longText('payload');
            $table->timestamps();
            $table->primary(['session_id', 'key_type', 'key_id']);
            $table->foreign('session_id')->references('id')->on('sessions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wa_auth_keys');
        Schema::dropIfExists('wa_auth_sessions');
    }
};
