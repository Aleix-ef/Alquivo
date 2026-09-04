<?php

namespace App\Domain\Finance\Models;

use App\Domain\Properties\Models\Property;
use Illuminate\Database\Eloquent\Model;

class RecurringRule extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2', 'starts_on' => 'date', 'next_date' => 'date',
            'ends_on' => 'date', 'active' => 'boolean',
        ];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }
}
