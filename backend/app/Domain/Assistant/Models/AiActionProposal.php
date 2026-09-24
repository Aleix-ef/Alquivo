<?php

namespace App\Domain\Assistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiActionProposal extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['payload', 'property_snapshot', 'payload_hash'];

    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'property_snapshot' => 'encrypted:array',
            'result' => 'array',
            'revision' => 'integer',
            'schema_version' => 'integer',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function run()
    {
        return $this->belongsTo(AiRun::class, 'run_id');
    }
}
