<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->string('subscription_status', 30)->default('active')->after('storage_limit_bytes');
            $table->timestamp('plan_changed_at')->nullable()->after('subscription_status');
            $table->string('billing_customer_id')->nullable()->unique()->after('plan_changed_at');
            $table->string('billing_subscription_id')->nullable()->unique()->after('billing_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('portfolios', fn (Blueprint $table) => $table->dropColumn(['subscription_status', 'plan_changed_at', 'billing_customer_id', 'billing_subscription_id']));
    }
};
