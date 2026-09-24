<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anonymous provider totals survive account deletion; deleting an account must not reset spending limits.
        Schema::create('ai_provider_usage', function (Blueprint $table) {
            $table->string('provider', 30);
            $table->date('month');
            $table->unsignedBigInteger('estimated_cost_nano_usd')->default(0);
            $table->unsignedBigInteger('reserved_cost_nano_usd')->default(0);
            $table->primary(['provider', 'month']);
        });
        foreach (DB::table('ai_runs')->selectRaw('billing_month, SUM(estimated_cost_nano_usd) as spent, SUM(reserved_cost_nano_usd) as reserved')->groupBy('billing_month')->get() as $month) {
            DB::table('ai_provider_usage')->insert(['provider' => 'openai', 'month' => $month->billing_month,
                'estimated_cost_nano_usd' => $month->spent, 'reserved_cost_nano_usd' => $month->reserved]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_usage');
    }
};
