<?php

namespace App\Domain\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['guest_key_hash'];

    protected function casts(): array
    {
        return ['user_id' => 'integer', 'name' => 'encrypted', 'email' => 'encrypted', 'subject' => 'encrypted', 'last_message_id' => 'integer', 'customer_read_id' => 'integer', 'team_read_id' => 'integer'];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'conversation_id');
    }
}
