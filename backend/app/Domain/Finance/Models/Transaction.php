<?php

namespace App\Domain\Finance\Models;

use App\Domain\Properties\Models\Property;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'transaction_date' => 'date', 'due_date' => 'date'];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function charge()
    {
        return $this->belongsTo(RentCharge::class, 'rent_charge_id');
    }
}
