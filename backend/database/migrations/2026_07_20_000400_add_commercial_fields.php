<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
            $table->string('terms_version', 20)->nullable()->after('terms_accepted_at');
        });
        Schema::table('portfolios', function (Blueprint $table) {
            $table->string('plan', 30)->default('free')->after('country_code');
            $table->unsignedBigInteger('storage_limit_bytes')->default(52428800)->after('plan');
        });
    }

    public function down(): void
    {
        Schema::table('portfolios', fn (Blueprint $table) => $table->dropColumn(['plan', 'storage_limit_bytes']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['terms_accepted_at', 'terms_version']));
    }
};
