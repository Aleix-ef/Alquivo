<?php

namespace App\Domain\Fiscality\Models;

use Illuminate\Database\Eloquent\Model;

class TaxYear extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['revision' => 0];

    protected function casts(): array
    {
        return ['profile' => 'encrypted:array', 'year' => 'integer', 'revision' => 'integer'];
    }

    public function records()
    {
        return $this->hasMany(PropertyTaxRecord::class);
    }
}
