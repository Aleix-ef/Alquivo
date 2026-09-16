<?php

namespace App\Domain\Assistant\Models;

use Illuminate\Database\Eloquent\Model;

class AiMessage extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
