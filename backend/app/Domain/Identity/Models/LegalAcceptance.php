<?php

namespace App\Domain\Identity\Models;

use Illuminate\Database\Eloquent\Model;

final class LegalAcceptance extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    // Evidence is restricted server-side, never part of a user/assistant API response.
    protected $hidden = ['subject_email'];

    protected function casts(): array
    {
        return ['subject_email' => 'encrypted', 'recorded_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
