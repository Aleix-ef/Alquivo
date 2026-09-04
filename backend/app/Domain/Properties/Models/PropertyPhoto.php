<?php

namespace App\Domain\Properties\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyPhoto extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['storage_key'];

    protected $appends = ['url'];

    protected function casts(): array
    {
        return ['is_cover' => 'boolean'];
    }

    public function getUrlAttribute(): string
    {
        return url("/api/v1/property-photos/{$this->id}");
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}
