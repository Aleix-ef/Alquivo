<?php

namespace App\Domain\Fiscality\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyTaxRecord extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['inputs' => 'encrypted:array', 'revision' => 'integer'];
    }
}
