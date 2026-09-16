<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_monthly_usage', function (Blueprint $table) {
            $table->unsignedBigInteger('input_tokens')->default(0)->after('requests');
            $table->unsignedBigInteger('output_tokens')->default(0)->after('input_tokens');
        });

        $month = now()->startOfMonth();
        $usage = DB::table('ai_messages')
            ->join('ai_conversations', 'ai_conversations.id', '=', 'ai_messages.conversation_id')
            ->where('ai_messages.role', 'assistant')
            ->whereBetween('ai_messages.created_at', [$month, $month->copy()->endOfMonth()])
            ->select('portfolio_id', 'user_id')
            ->selectRaw('COALESCE(SUM(input_tokens), 0) AS input_tokens')
            ->selectRaw('COALESCE(SUM(output_tokens), 0) AS output_tokens')
            ->groupBy('portfolio_id', 'user_id')
            ->get();
        foreach ($usage as $row) {
            DB::table('ai_monthly_usage')->where([
                'portfolio_id' => $row->portfolio_id,
                'user_id' => $row->user_id,
                'month' => $month->toDateString(),
            ])->update([
                'input_tokens' => $row->input_tokens,
                'output_tokens' => $row->output_tokens,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('ai_monthly_usage', fn (Blueprint $table) => $table->dropColumn(['input_tokens', 'output_tokens']));
    }
};
