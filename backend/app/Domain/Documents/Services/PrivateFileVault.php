<?php

namespace App\Domain\Documents\Services;

use Illuminate\Encryption\Encrypter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Authenticated encryption, with the logical path bound inside the payload. */
final class PrivateFileVault
{
    public const HEADER = "ALQUIVO-VAULT-1\n";

    public function encrypter(): Encrypter
    {
        $keys = config('vault.key') ? [config('vault.key')] : json_decode(
            is_readable(config('vault.key_file')) ? file_get_contents(config('vault.key_file')) : '[]', true, flags: JSON_THROW_ON_ERROR,
        );
        if (! is_array($keys) || $keys === []) {
            throw new RuntimeException('Private file keyring unavailable');
        }
        $decoded = array_map(function ($key) {
            $raw = is_string($key) && str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;
            if (! is_string($raw) || ! Encrypter::supported($raw, 'aes-256-gcm')) {
                throw new RuntimeException('Invalid private file key');
            }

            return $raw;
        }, $keys);

        return (new Encrypter(array_shift($decoded), 'aes-256-gcm'))->previousKeys($decoded);
    }

    public function encrypt(string $key, string $bytes): string
    {
        return self::HEADER.$this->encrypter()->encryptString($key."\0".$bytes);
    }

    public function decrypt(string $key, string $payload): string
    {
        if (! str_starts_with($payload, self::HEADER)) {
            throw new RuntimeException('Private file must be encrypted before use');
        }
        $plain = $this->encrypter()->decryptString(substr($payload, strlen(self::HEADER)));
        $binding = $key."\0";
        if (! str_starts_with($plain, $binding)) {
            throw new RuntimeException('Private file path mismatch');
        }

        return substr($plain, strlen($binding));
    }

    public function store(UploadedFile $file, string $directory): string
    {
        $key = $directory.'/'.Str::uuid().'.enc';
        Storage::disk('local')->put($key, $this->encrypt($key, $file->getContent()));

        return $key;
    }

    public function read(string $key): string
    {
        abort_unless(Storage::disk('local')->exists($key), 404);

        return $this->decrypt($key, Storage::disk('local')->get($key));
    }
}
