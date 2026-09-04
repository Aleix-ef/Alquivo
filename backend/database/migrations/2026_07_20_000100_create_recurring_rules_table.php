<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('direction')->default('expense');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->string('frequency');
            $table->date('starts_on');
            $table->date('next_date');
            $table->date('ends_on')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['portfolio_id', 'active', 'next_date']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_rule_id')->nullable()->after('rent_charge_id')->constrained()->restrictOnDelete();
            $table->unique(['recurring_rule_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['recurring_rule_id', 'transaction_date']);
            $table->dropConstrainedForeignId('recurring_rule_id');
        });
        Schema::dropIfExists('recurring_rules');
    }
};
