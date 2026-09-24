<?php

namespace App\Console\Commands;

use App\Domain\Documents\Services\PrivateFileDeletion;
use App\Domain\Support\Models\SupportConversation;
use App\Domain\Support\Services\SupportChat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneSupport extends Command
{
    protected $signature = 'support:prune';

    protected $description = 'Eliminar conversaciones inactivas de soporte y programar el borrado seguro de sus adjuntos';

    public function handle(SupportChat $chat, PrivateFileDeletion $cleanup): int
    {
        $cutoff = now()->subDays(max(1, (int) config('support.chat_retention_days', 180)));
        $count = 0;
        SupportConversation::where('updated_at', '<', $cutoff)->select('id')->chunkById(100, function ($threads) use ($chat, $cleanup, $cutoff, &$count) {
            foreach ($threads as $thread) {
                $id = DB::transaction(function () use ($thread, $chat, $cutoff) {
                    $current = SupportConversation::whereKey($thread->id)->where('updated_at', '<', $cutoff)->lockForUpdate()->first();

                    return $current ? $chat->scheduleDeletion($current) : null;
                });
                if ($id) {
                    $cleanup->process($id);
                    $count++;
                }
            }
        });
        $this->info("Conversaciones eliminadas: {$count}. Los archivos pendientes de borrado se reintentan automáticamente.");

        return self::SUCCESS;
    }
}
