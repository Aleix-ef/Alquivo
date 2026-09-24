<?php

namespace App\Domain\Assistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiDocumentExtraction extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['draft', 'file_hash'];

    protected function casts(): array
    {
        return ['draft' => 'encrypted:array', 'receipt' => 'array', 'expires_at' => 'datetime',
            'started_at' => 'datetime', 'reviewed_at' => 'datetime', 'confirmed_at' => 'datetime'];
    }
}
