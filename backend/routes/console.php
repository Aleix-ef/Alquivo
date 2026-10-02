<?php

use App\Domain\Assistant\Models\AiActionProposal;
use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiDocumentExtraction;
use App\Domain\Assistant\Models\AiMessage;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Documents\Services\PrivateFileDeletion;
use App\Domain\Identity\Models\LegalAcceptance;
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
    AiDocumentExtraction::where('expires_at', '<', now())->update(['draft' => null]);
    AiDocumentExtraction::where('expires_at', '<', now())->whereNotIn('status', ['confirmed', 'cancelled'])->update(['status' => 'expired']);
    $cutoff = now()->subDays(config('assistant.retention_days'));
    AiMessage::where('created_at', '<', $cutoff)->delete();
    AiActionProposal::where('created_at', '<', $cutoff)->delete();
    AiActionProposal::whereIn('run_id', AiRun::whereIn('conversation_id', AiConversation::where('updated_at', '<', $cutoff)->select('id'))->select('id'))->delete();
    AiConversation::where('updated_at', '<', $cutoff)->delete();
    $metricsCutoff = now()->startOfMonth()->subMonths((int) config('ai.limits.metrics_retention_months', 12));
    DB::table('ai_monthly_usage')->where('month', '<', $metricsCutoff->toDateString())->delete();
    DB::table('ai_provider_usage')->where('month', '<', $metricsCutoff->toDateString())->delete();
    AiRun::where('billing_month', '<', $metricsCutoff->toDateString())->delete();
    $this->info('Historial y propuestas caducados eliminados. Se conservan los contadores y las métricas recientes, sin el contenido del chat.');
})->purpose('Eliminar el historial de IA tras 30 días');
Schedule::command('assistant:prune')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('support:prune')->dailyAt('02:45')->withoutOverlapping();

Artisan::command('legal:prune', function () {
    $deleted = LegalAcceptance::where('expires_at', '<=', now())->delete();
    $this->info('Evidencias legales caducadas eliminadas: '.$deleted);
})->purpose('Eliminar evidencias de aceptación cuyo plazo de conservación ha finalizado');
Schedule::command('legal:prune')->dailyAt('03:00')->withoutOverlapping();

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
