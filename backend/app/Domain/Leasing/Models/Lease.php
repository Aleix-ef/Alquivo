<?php

namespace App\Domain\Leasing\Models;

use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Properties\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lease extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'monthly_rent' => 'decimal:2', 'deposit_amount' => 'decimal:2'];
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    public function participants()
    {
        return $this->belongsToMany(Contact::class, 'lease_participants')->withPivot(['role', 'is_primary']);
    }

    public function charges()
    {
        return $this->hasMany(RentCharge::class);
    }

    public function renewedFrom()
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    public function renewal()
    {
        return $this->hasOne(self::class, 'renewed_from_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
