<?php

use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiMessage;
use App\Domain\Documents\Services\PrivateFileDeletion;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rent:generate')->dailyAt('00:10')->withoutOverlapping();
Schedule::command('finance:generate-recurring')->dailyAt('00:20')->withoutOverlapping();

Artisan::command('assistant:prune', function () {
    $cutoff = now()->subDays(config('assistant.retention_days'));
    AiMessage::where('created_at', '<', $cutoff)->delete();
    AiConversation::where('updated_at', '<', $cutoff)->delete();
    DB::table('ai_monthly_usage')->where('month', '<', now()->startOfMonth()->toDateString())->delete();
    $this->info('Historial caducado eliminado. El cupo del mes actual se conserva.');
})->purpose('Eliminar el historial de IA tras 30 días');
Schedule::command('assistant:prune')->dailyAt('02:30')->withoutOverlapping();

Artisan::command('storage:prune-private', function () {
    $failed = 0;
    DB::table('private_file_deletions')->orderBy('id')->chunkById(100, function ($items) use (&$failed) {
        foreach ($items as $item) {
            if (! app(PrivateFileDeletion::class)->process($item->id)) {
                $failed++;
            }
        }
    });
    $this->info('Limpieza completada. Pendientes: '.$failed);

    return $failed ? 1 : 0;
})->purpose('Reintentar eliminaciones pendientes de archivos privados');
Schedule::command('storage:prune-private')->everyTenMinutes()->withoutOverlapping();
