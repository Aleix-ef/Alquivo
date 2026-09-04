<?php

namespace App\Domain\Finance\Models;

use App\Domain\Leasing\Models\Lease;
use Illuminate\Database\Eloquent\Model;

class RentCharge extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'amount' => 'decimal:2', 'paid_amount' => 'decimal:2'];
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
