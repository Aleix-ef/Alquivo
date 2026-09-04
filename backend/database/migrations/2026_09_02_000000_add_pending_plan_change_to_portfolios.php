<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->string('pending_plan', 30)->nullable()->after('plan_changed_at');
            $table->string('pending_billing_period', 20)->nullable()->after('pending_plan');
            $table->timestamp('pending_plan_effective_at')->nullable()->after('pending_billing_period');
            $table->string('billing_schedule_id')->nullable()->after('billing_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('portfolios', fn (Blueprint $table) => $table->dropColumn([
            'pending_plan',
            'pending_billing_period',
            'pending_plan_effective_at',
            'billing_schedule_id',
        ]));
    }
};
