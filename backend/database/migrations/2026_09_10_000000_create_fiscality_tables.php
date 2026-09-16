<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->text('profile')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->timestamps();
            $table->unique(['portfolio_id', 'user_id', 'year']);
        });
        Schema::create('property_tax_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->longText('inputs');
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();
            $table->unique(['tax_year_id', 'property_id']);
        });
        Schema::create('tax_report_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('fingerprint', 64);
            $table->longText('payload');
            $table->timestamp('created_at');
            $table->unique(['portfolio_id', 'user_id', 'year', 'fingerprint'], 'tax_report_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_report_snapshots');
        Schema::dropIfExists('property_tax_records');
        Schema::dropIfExists('tax_years');
    }
};
