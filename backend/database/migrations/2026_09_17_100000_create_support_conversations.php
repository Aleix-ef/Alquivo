<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_agents', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
        Schema::create('support_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('creation_key');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_key_hash', 64)->nullable()->index();
            $table->text('name');
            $table->text('email')->nullable();
            $table->text('subject');
            $table->string('status', 24)->default('waiting_support');
            $table->string('last_author', 16)->default('customer');
            $table->unsignedBigInteger('last_message_id')->default(0);
            $table->unsignedBigInteger('customer_read_id')->default(0);
            $table->unsignedBigInteger('team_read_id')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
            $table->index(['status', 'updated_at']);
            $table->unique(['user_id', 'creation_key']);
            $table->unique(['guest_key_hash', 'creation_key']);
        });
        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('conversation_id')->constrained('support_conversations')->cascadeOnDelete();
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_role', 16);
            $table->uuid('client_id');
            $table->string('request_hash', 64)->nullable();
            $table->text('body');
            $table->timestamps();
            $table->unique(['conversation_id', 'client_id']);
            $table->index(['conversation_id', 'id']);
        });
        Schema::create('support_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('message_id')->constrained('support_messages')->cascadeOnDelete();
            $table->string('storage_key');
            $table->string('filename', 40);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_attachments');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_conversations');
        Schema::dropIfExists('support_agents');
    }
};
