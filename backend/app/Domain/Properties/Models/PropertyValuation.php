<?php

namespace App\Domain\Properties\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyValuation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'valued_at' => 'date'];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
