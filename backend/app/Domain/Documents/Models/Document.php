<?php

namespace App\Domain\Documents\Models;

use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Properties\Models\Property;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['storage_key'];

    protected function casts(): array
    {
        return ['issued_at' => 'date', 'expires_at' => 'date'];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function lease()
    {
        return $this->belongsTo(Lease::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}
