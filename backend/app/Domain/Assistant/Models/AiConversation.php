<?php

namespace App\Domain\Assistant\Models;

use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AiConversation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_message_at' => 'datetime'];
    }

    public function portfolio()
    {
        return $this->belongsTo(Portfolio::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function messages()
    {
        return $this->hasMany(AiMessage::class, 'conversation_id');
    }
}
