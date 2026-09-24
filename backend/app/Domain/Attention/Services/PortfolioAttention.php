<?php

namespace App\Domain\Attention\Services;

use App\Domain\Attention\Models\Issue;
use App\Domain\Attention\Models\Reminder;
use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Queries\RentChargeBalances;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

/** Read-only facts shared by dashboard and assistant. Callers authorize membership;
 * all entities and relationship edges are scoped here. No provider dependency.
 */
final class PortfolioAttention
{
    public function snapshot(Portfolio $portfolio, ?int $propertyId = null): array
    {
        if ($propertyId !== null) {
            $portfolio->properties()->findOrFail($propertyId);
        }
        $now = CarbonImmutable::now(config('app.timezone'));
        $today = $now->toDateString();
        $windows = [];
        foreach (['rent', 'lease', 'issue', 'document', 'reminder'] as $kind) {
            $windows[$kind] = max(0, min(365, (int) config("attention.{$kind}_days")));
        }
        $cutoff = fn ($kind) => $now->addDays($windows[$kind])->toDateString();
        $items = [];
        $balances = app(RentChargeBalances::class);
        $paid = $balances->paid($portfolio);
        $charges = $balances->charges($portfolio, $propertyId)->where('status', '!=', 'cancelled')
            ->whereHas('lease', fn ($q) => $q->whereIn('status', ['active', 'ended']))
            ->whereDate('due_date', '<=', $cutoff('rent'))->where('amount', '>', clone $paid)
            ->select('rent_charges.*')->selectSub($paid, 'recorded_paid')->with('lease.property')->get();
        $rentTotal = '0.00';
        foreach ($charges as $charge) {
            // Ended leases retain historical debt, never future-period charges.
            if ($charge->lease->status === 'ended' && ($charge->lease->end_date !== null
                ? $charge->period > $charge->lease->end_date->format('Y-m') : $charge->due_date->toDateString() > $today)) {
                continue;
            }
            $received = bcadd((string) $charge->recorded_paid, '0', 2);
            $remaining = bcsub($charge->amount, $received, 2);
            if (bccomp($remaining, '0', 2) <= 0) {
                continue;
            }
            $date = $charge->due_date->toDateString();
            $partial = bccomp($received, '0', 2) > 0;
            $type = $date < $today ? 'rent_overdue' : ($partial ? 'rent_partial' : 'rent_pending');
            $items[] = $this->item('rents', $type, 'rent_charge', $charge->id, $charge->lease->property,
                $type === 'rent_overdue' ? 'Alquiler con saldo atrasado' : ($partial ? 'Alquiler cobrado parcialmente' : 'Alquiler pendiente'),
                $date, $today, $remaining, '/leases/'.$charge->lease_id, 'Ver mensualidad',
                ['rule' => 'recorded_rent_balance', 'lease_id' => $charge->lease_id, 'period' => $charge->period,
                    'amount' => $charge->amount, 'recorded_paid' => $received, 'remaining' => $remaining,
                    'payment_state' => $partial ? 'partial' : 'pending', 'due_date' => $date, 'window_days' => $windows['rent']],
                'Saldo de la mensualidad '.$charge->period.' según los cobros registrados.');
            $rentTotal = bcadd($rentTotal, $remaining, 2);
        }
        $leases = $this->scoped(Lease::query(), $portfolio, $propertyId, false)->where('status', 'active')
            ->whereNotNull('end_date')->whereDate('end_date', '<=', $cutoff('lease'))->with('property')->get();
        foreach ($leases as $lease) {
            $date = $lease->end_date->toDateString();
            $items[] = $this->item('leases', $date < $today ? 'lease_end_passed' : 'lease_expiring', 'lease', $lease->id, $lease->property,
                $date < $today ? 'Fecha de fin superada' : 'Contrato próximo a su fecha de fin', $date, $today, null,
                '/leases/'.$lease->id, 'Revisar contrato', ['rule' => 'active_lease_end', 'status' => 'active', 'end_date' => $date, 'window_days' => $windows['lease']],
                'El contrato sigue marcado como activo. La fecha registrada no determina su finalización legal.');
        }
        $issues = $this->scoped(Issue::query(), $portfolio, $propertyId, false)->whereNotIn('status', ['resolved', 'cancelled'])
            ->where(fn ($q) => $q->where('priority', 'high')->orWhereDate('due_date', '<=', $cutoff('issue')))->with('property')->get();
        foreach ($issues as $issue) {
            $date = $issue->due_date?->toDateString();
            $items[] = $this->item('issues', 'issue', 'issue', $issue->id, $issue->property, 'Incidencia pendiente', $date, $today, null,
                '/issues#issue-'.$issue->id, 'Ver incidencia', ['rule' => 'open_issue_due_or_flagged', 'status' => $issue->status, 'recorded_priority' => $issue->priority, 'due_date' => $date, 'window_days' => $windows['issue']],
                $issue->title, $issue->priority === 'high' && ($date === null || $date > $cutoff('issue')));
        }
        $documents = $this->scoped(Document::query(), $portfolio, $propertyId)->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $cutoff('document'))
            ->where(fn ($q) => $q->whereNull('lease_id')->orWhereHas('lease', fn ($l) => $l->where('portfolio_id', $portfolio->id)->whereHas('property', fn ($p) => $p->where('portfolio_id', $portfolio->id))))
            ->where(fn ($q) => $q->whereNull('transaction_id')->orWhereHas('transaction', fn ($t) => $t->where('portfolio_id', $portfolio->id)))
            ->with('property')->get();
        foreach ($documents as $document) {
            $date = $document->expires_at->toDateString();
            $items[] = $this->item('documents', 'document_expiry', 'document', $document->id, $document->property,
                'Fecha de revisión documental', $date, $today, null, '/documents#document-'.$document->id, 'Ver documento',
                ['rule' => 'recorded_document_expiry', 'expires_at' => $date, 'window_days' => $windows['document']], $document->name.' · Fecha registrada, no verificación de validez legal.');
        }
        $reminders = $this->scoped(Reminder::query(), $portfolio, $propertyId)->whereNull('completed_at')
            ->whereDate('starts_at', '<=', $cutoff('reminder'))->with('property')->get();
        foreach ($reminders as $reminder) {
            $items[] = $this->item('reminders', 'reminder', 'reminder', $reminder->id, $reminder->property, 'Recordatorio sin completar',
                $reminder->starts_at->toDateString(), $today, null, '/calendar?date='.$reminder->starts_at->toDateString().'#reminder-'.$reminder->id, 'Ver recordatorio',
                ['rule' => 'incomplete_recorded_reminder', 'starts_at' => $reminder->starts_at->toIso8601String(), 'window_days' => $windows['reminder']], $reminder->title);
        }
        usort($items, fn ($a, $b) => [$a['priority']['rank'], $a['date'] ?? '9999', $a['id']] <=> [$b['priority']['rank'], $b['date'] ?? '9999', $b['id']]);
        $counts = array_fill_keys(['rents', 'leases', 'issues', 'documents', 'reminders'], 0);
        $overdueRents = 0;
        foreach ($items as $item) {
            $counts[$item['group']]++;
            $overdueRents += $item['type'] === 'rent_overdue' ? 1 : 0;
        }

        return ['as_of' => $now->toIso8601String(), 'timezone' => $now->timezoneName, 'currency' => $portfolio->currency,
            'rules_version' => '2026-09-24', 'windows_days' => $windows, 'total' => count($items), 'counts' => $counts,
            'rents_summary' => ['total_count' => $counts['rents'], 'pending_amount' => $rentTotal, 'overdue_count' => $overdueRents],
            'items' => $items, 'app_path' => '/dashboard',
            'note' => 'Sólo registros existentes dentro de los plazos indicados; no verifica información ausente ni genera obligaciones. Importes decimales exactos.'];
    }

    private function scoped(Builder $query, Portfolio $portfolio, ?int $propertyId, bool $optionalProperty = true): Builder
    {
        $query->where('portfolio_id', $portfolio->id)->where(function ($q) use ($portfolio, $optionalProperty) {
            $q->whereHas('property', fn ($p) => $p->where('portfolio_id', $portfolio->id));
            if ($optionalProperty) {
                $q->orWhereNull('property_id');
            }
        });
        if ($propertyId !== null) {
            $query->where('property_id', $propertyId);
        }

        return $query;
    }

    private function item(string $group, string $type, string $entityType, int $id, $property, string $title, ?string $date, string $today, ?string $amount, string $path, string $action, array $evidence, string $detail, bool $flagged = false): array
    {
        [$code, $rank, $label] = $date !== null && $date < $today ? ['overdue', 0, 'Fecha superada']
            : ($date === $today ? ['today', 1, 'Hoy'] : ($flagged || $date === null ? ['review', 2, 'Marcada para revisar'] : ['upcoming', 3, 'Próximamente']));

        return ['id' => $entityType.':'.$id, 'group' => $group, 'type' => $type, 'entity' => ['type' => $entityType, 'id' => $id],
            'property' => $property ? ['id' => $property->id, 'name' => $property->name] : null,
            'priority' => ['code' => $code, 'rank' => $rank, 'label' => $label], 'title' => $title,
            'description' => ['text' => $detail, 'basis' => 'recorded_data'], 'date' => $date, 'amount' => $amount,
            'action' => ['label' => $action, 'path' => $path, 'mode' => 'navigate'], 'evidence' => $evidence];
    }
}
