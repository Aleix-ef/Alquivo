<?php

namespace App\Domain\Assistant\Models;

use Illuminate\Database\Eloquent\Model;

class AiRunStep extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
