<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leases', function (Blueprint $table) {
            // Null preserves legacy single-day contracts: start equals payment_day.
            $table->unsignedTinyInteger('payment_day_start')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leases', fn (Blueprint $table) => $table->dropColumn('payment_day_start'));
    }
};
