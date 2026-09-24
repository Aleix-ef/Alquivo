<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('ai_conversations')->nullOnDelete();
            $table->uuid('client_request_id');
            $table->string('request_hash', 64);
            $table->string('feature', 40)->default('chat');
            $table->string('plan', 30);
            $table->string('status', 30)->default('processing');
            $table->string('routing_version', 80);
            $table->date('billing_month');
            $table->foreignId('user_message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->foreignId('assistant_message_id')->nullable()->constrained('ai_messages')->nullOnDelete();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('estimated_cost_nano_usd')->default(0);
            $table->unsignedBigInteger('reserved_cost_nano_usd')->default(0);
            $table->boolean('cost_incomplete')->default(false);
            $table->string('error_code', 80)->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'client_request_id']);
            $table->index(['portfolio_id', 'billing_month']);
            $table->index(['status', 'created_at']);
        });
        Schema::create('ai_run_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('run_id')->constrained('ai_runs')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('status', 30);
            $table->string('tool', 100)->nullable();
            $table->string('provider', 30)->nullable();
            $table->string('model', 120)->nullable();
            $table->string('provider_request_id', 200)->nullable();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->unsignedBigInteger('cached_input_tokens')->default(0);
            $table->unsignedBigInteger('cache_write_tokens')->default(0);
            $table->unsignedBigInteger('estimated_cost_nano_usd')->nullable();
            $table->unsignedBigInteger('reserved_cost_nano_usd')->default(0);
            $table->string('pricing_version', 100)->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['run_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_run_steps');
        Schema::dropIfExists('ai_runs');
    }
};
