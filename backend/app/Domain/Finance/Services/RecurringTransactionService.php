<?php

namespace App\Domain\Finance\Services;

use App\Domain\Finance\Models\RecurringRule;
use App\Domain\Finance\Models\Transaction;
use Carbon\Carbon;

class RecurringTransactionService
{
    public function generateDue(RecurringRule $rule, ?Carbon $until = null): int
    {
        $until ??= today();
        $created = 0;

        while ($rule->active && $rule->next_date->lte($until)) {
            if ($rule->ends_on && $rule->next_date->gt($rule->ends_on)) {
                $rule->update(['active' => false]);
                break;
            }
            $transaction = Transaction::firstOrCreate(
                ['recurring_rule_id' => $rule->id, 'transaction_date' => $rule->next_date->toDateString()],
                [
                    'portfolio_id' => $rule->portfolio_id, 'property_id' => $rule->property_id,
                    'direction' => $rule->direction, 'category' => $rule->category,
                    'description' => $rule->description, 'amount' => $rule->amount,
                    'due_date' => $rule->next_date, 'status' => 'pending',
                ],
            );
            $created += $transaction->wasRecentlyCreated ? 1 : 0;
            $rule->next_date = $this->nextDate($rule->next_date, $rule->frequency);
            $rule->save();
        }

        return $created;
    }

    private function nextDate(Carbon $date, string $frequency): Carbon
    {
        return match ($frequency) {
            'quarterly' => $date->copy()->addMonthsNoOverflow(3),
            'yearly' => $date->copy()->addYearNoOverflow(),
            default => $date->copy()->addMonthNoOverflow(),
        };
    }
}
