<?php

namespace App\Domain\Attention\Models;

use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Properties\Models\Property;
use Illuminate\Database\Eloquent\Model;

class Issue extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reported_at' => 'date', 'due_date' => 'date', 'resolved_at' => 'datetime', 'estimated_cost' => 'decimal:2', 'actual_cost' => 'decimal:2'];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function assignedContact()
    {
        return $this->belongsTo(Contact::class, 'assigned_contact_id');
    }

    public function expenseTransaction()
    {
        return $this->belongsTo(Transaction::class, 'expense_transaction_id');
    }
}
