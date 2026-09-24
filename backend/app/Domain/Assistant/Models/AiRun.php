<?php

namespace App\Domain\Assistant\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiRun extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $hidden = ['request_hash'];

    protected function casts(): array
    {
        return ['billing_month' => 'date', 'finished_at' => 'datetime', 'cost_incomplete' => 'boolean'];
    }

    public function steps()
    {
        return $this->hasMany(AiRunStep::class, 'run_id');
    }

    public function publicStatus(): array
    {
        return ['id' => $this->id, 'client_request_id' => $this->client_request_id, 'status' => $this->status];
    }
}
