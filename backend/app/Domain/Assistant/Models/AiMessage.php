<?php

namespace App\Domain\Assistant\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use InvalidArgumentException;

class AiMessage extends Model
{
    protected $guarded = ['id', 'content_encrypted', 'metadata_encrypted'];

    protected $hidden = ['content_encrypted', 'metadata_encrypted'];

    protected function content(): Attribute
    {
        return Attribute::make(
            // Transitional reads support unmigrated rows. A corrupt ciphertext never falls back to plaintext.
            get: fn ($value, array $attributes) => ($attributes['content_encrypted'] ?? null) !== null
                ? Crypt::decryptString($attributes['content_encrypted'])
                : $value,
            set: function ($value): array {
                if (! is_string($value)) {
                    throw new InvalidArgumentException('El mensaje debe ser texto.');
                }

                return ['content' => '', 'content_encrypted' => Crypt::encryptString($value)];
            },
        );
    }

    protected function metadata(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes): ?array {
                $encoded = ($attributes['metadata_encrypted'] ?? null) !== null
                    ? Crypt::decryptString($attributes['metadata_encrypted'])
                    : $value;

                return $encoded === null ? null : json_decode($encoded, true, 512, JSON_THROW_ON_ERROR);
            },
            set: function ($value): array {
                if ($value !== null && ! is_array($value)) {
                    throw new InvalidArgumentException('Los metadatos del mensaje deben ser un objeto estructurado.');
                }

                return [
                    'metadata' => null,
                    'metadata_encrypted' => $value === null ? null : Crypt::encryptString(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                ];
            },
        );
    }

    public function conversation()
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
