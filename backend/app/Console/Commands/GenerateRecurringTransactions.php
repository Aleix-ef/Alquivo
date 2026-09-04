<?php

namespace App\Console\Commands;

use App\Domain\Finance\Models\RecurringRule;
use App\Domain\Finance\Services\RecurringTransactionService;
use Illuminate\Console\Command;

class GenerateRecurringTransactions extends Command
{
    protected $signature = 'finance:generate-recurring';

    protected $description = 'Genera los movimientos vencidos a partir de reglas recurrentes';

    public function handle(RecurringTransactionService $generator): int
    {
        $created = 0;
        RecurringRule::where('active', true)->whereDate('next_date', '<=', today())
            ->each(function (RecurringRule $rule) use ($generator, &$created) {
                $created += $generator->generateDue($rule);
            });
        $this->info("Movimientos creados: {$created}.");

        return self::SUCCESS;
    }
}
