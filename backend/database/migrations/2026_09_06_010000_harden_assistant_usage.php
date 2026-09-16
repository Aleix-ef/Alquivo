<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('assistant_enabled_at')->nullable();
            $table->string('assistant_notice_version', 30)->nullable();
        });
        // Independent of chat history: deleting messages never restores a spending quota.
        Schema::create('ai_monthly_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('month');
            $table->unsignedInteger('requests')->default(0);
            $table->unique(['portfolio_id', 'user_id', 'month']);
        });
        $month = now()->startOfMonth()->toDateString();
        $rows = DB::table('ai_messages')->join('ai_conversations', 'ai_conversations.id', '=', 'ai_messages.conversation_id')
            ->where('role', 'user')->where('ai_messages.created_at', '>=', $month)
            ->select('portfolio_id', 'user_id')->selectRaw('COUNT(*) AS requests')
            ->groupBy('portfolio_id', 'user_id')->get();
        foreach ($rows as $row) {
            DB::table('ai_monthly_usage')->insert([...get_object_vars($row), 'month' => $month]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_monthly_usage');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['assistant_enabled_at', 'assistant_notice_version']));
    }
};
