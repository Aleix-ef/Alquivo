<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_action_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('run_id')->constrained('ai_runs')->cascadeOnDelete();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('revision')->default(1);
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->text('payload'); // Laravel encrypted:array; never expose raw model serialization.
            $table->text('property_snapshot');
            $table->string('payload_hash', 64);
            $table->json('result')->nullable(); // Receipt IDs only, not a copy of financial records.
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->unique(['run_id', 'type']);
            $table->index(['portfolio_id', 'user_id', 'created_at']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_action_proposals');
    }
};
