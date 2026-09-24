<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('document_ai_accepted_at')->nullable();
            $table->string('document_ai_notice_version', 80)->nullable();
        });
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
        });
        Schema::create('ai_document_extractions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('run_id')->nullable()->constrained('ai_runs')->nullOnDelete();
            $table->string('kind', 20);
            $table->string('file_hash', 64);
            $table->unsignedSmallInteger('schema_version');
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->longText('draft')->nullable();
            $table->json('receipt')->nullable();
            $table->string('error_code', 60)->nullable();
            $table->string('notice_version', 80);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['portfolio_id', 'file_hash', 'kind', 'schema_version'], 'document_extraction_version_unique');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_document_extractions');
        Schema::table('documents', fn (Blueprint $table) => $table->dropConstrainedForeignId('transaction_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['document_ai_accepted_at', 'document_ai_notice_version']));
    }
};
