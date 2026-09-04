<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('currency', 3)->default('EUR');
            $table->char('country_code', 2)->default('ES');
            $table->timestamps();
        });

        Schema::create('portfolio_members', function (Blueprint $table) {
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner');
            $table->timestamps();
            $table->primary(['portfolio_id', 'user_id']);
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('housing');
            $table->string('address_line');
            $table->string('postal_code', 12)->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->char('country_code', 2)->default('ES');
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->decimal('acquisition_costs', 14, 2)->default(0);
            $table->decimal('current_value', 14, 2)->nullable();
            $table->date('valuation_date')->nullable();
            $table->decimal('outstanding_debt', 14, 2)->default(0);
            $table->decimal('area', 10, 2)->nullable();
            $table->unsignedSmallInteger('bedrooms')->nullable();
            $table->unsignedSmallInteger('bathrooms')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['portfolio_id', 'type']);
        });

        Schema::create('property_valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->date('valued_at');
            $table->string('source')->default('owner');
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->string('kind')->default('person');
            $table->string('name');
            $table->string('tax_id')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('draft');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('monthly_rent', 12, 2);
            $table->decimal('deposit_amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('payment_day')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['portfolio_id', 'status']);
        });

        Schema::create('lease_participants', function (Blueprint $table) {
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained()->restrictOnDelete();
            $table->string('role')->default('tenant');
            $table->boolean('is_primary')->default(false);
            $table->primary(['lease_id', 'contact_id']);
        });

        Schema::create('rent_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lease_id')->constrained()->restrictOnDelete();
            $table->string('period', 7);
            $table->date('due_date');
            $table->decimal('amount', 12, 2);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->unique(['lease_id', 'period']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('lease_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('rent_charge_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('direction');
            $table->string('category');
            $table->string('description');
            $table->decimal('amount', 12, 2);
            $table->date('transaction_date');
            $table->date('due_date')->nullable();
            $table->string('status')->default('paid');
            $table->string('payment_method')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['portfolio_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('rent_charges');
        Schema::dropIfExists('lease_participants');
        Schema::dropIfExists('leases');
        Schema::dropIfExists('contacts');
        Schema::dropIfExists('property_valuations');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('portfolio_members');
        Schema::dropIfExists('portfolios');
    }
};
