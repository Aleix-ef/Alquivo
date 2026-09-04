<?php

namespace App\Domain\Attention\Models;

use App\Domain\Properties\Models\Property;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
