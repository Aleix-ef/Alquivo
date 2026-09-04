<?php

namespace App\Domain\Leasing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    public function leases()
    {
        return $this->belongsToMany(Lease::class, 'lease_participants')->withPivot(['role', 'is_primary']);
    }
}
