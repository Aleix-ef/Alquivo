<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_admins', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->string('email')->unique();
            $table->uuid('grant_version')->unique();
            $table->timestamps();
        });
        Schema::table('users', fn (Blueprint $table) => $table->index('created_at', 'users_registration_date_index'));
        Schema::table('ai_runs', fn (Blueprint $table) => $table->index('created_at', 'ai_runs_created_at_index'));
    }

    public function down(): void
    {
        Schema::table('ai_runs', fn (Blueprint $table) => $table->dropIndex('ai_runs_created_at_index'));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('users_registration_date_index'));
        Schema::dropIfExists('business_admins');
    }
};
