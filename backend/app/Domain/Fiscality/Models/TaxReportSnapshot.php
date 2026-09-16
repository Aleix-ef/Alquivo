<?php

namespace App\Domain\Fiscality\Models;

use Illuminate\Database\Eloquent\Model;

class TaxReportSnapshot extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'year' => 'integer'];
    }
}
