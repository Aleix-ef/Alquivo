<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Only the account email, encrypted with APP_KEY; no chat, IP or documents.
            $table->text('subject_email');
            $table->string('scope', 24);
            $table->string('action', 24);
            $table->string('version', 80);
            $table->timestamp('recorded_at');
            $table->timestamp('expires_at')->nullable()->index();
            $table->index(['user_id', 'scope']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_acceptances');
    }
};
