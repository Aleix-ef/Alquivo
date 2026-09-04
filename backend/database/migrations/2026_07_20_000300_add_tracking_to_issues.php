<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->foreignId('assigned_contact_id')->nullable()->after('property_id')
                ->constrained('contacts')->nullOnDelete();
            $table->foreignId('expense_transaction_id')->nullable()->after('actual_cost')
                ->constrained('transactions')->nullOnDelete();
            $table->unique('expense_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropUnique(['expense_transaction_id']);
            $table->dropConstrainedForeignId('expense_transaction_id');
            $table->dropConstrainedForeignId('assigned_contact_id');
        });
    }
};
