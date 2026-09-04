<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('category')->default('other');
            $table->string('storage_key');
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->date('issued_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['portfolio_id', 'category']);
        });

        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->string('priority')->default('medium');
            $table->date('reported_at');
            $table->date('due_date')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->decimal('estimated_cost', 12, 2)->nullable();
            $table->decimal('actual_cost', 12, 2)->nullable();
            $table->timestamps();
            $table->index(['portfolio_id', 'status']);
        });

        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->dateTime('starts_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['portfolio_id', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
        Schema::dropIfExists('issues');
        Schema::dropIfExists('documents');
    }
};
