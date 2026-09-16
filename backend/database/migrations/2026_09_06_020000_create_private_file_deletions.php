<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // No foreign key: cleanup must survive the deletion of an account.
        Schema::create('private_file_deletions', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->boolean('directory')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_file_deletions');
    }
};
