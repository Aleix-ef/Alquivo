<?php

namespace App\Domain\Attention\Services;

use App\Domain\Attention\Models\Issue;
use App\Domain\Finance\Models\Transaction;

class IssueExpenseService
{
    public function create(Issue $issue, string $status, string $date): Transaction
    {
        if ($issue->expenseTransaction) {
            return $issue->expenseTransaction;
        }

        $transaction = Transaction::create([
            'portfolio_id' => $issue->portfolio_id,
            'property_id' => $issue->property_id,
            'direction' => 'expense',
            'category' => 'maintenance',
            'description' => $issue->title,
            'amount' => $issue->actual_cost,
            'transaction_date' => $date,
            'status' => $status,
            'notes' => 'Gasto generado desde la incidencia #'.$issue->id,
        ]);
        $issue->update(['expense_transaction_id' => $transaction->id]);

        return $transaction;
    }
}
