<?php

namespace App\Console\Commands;

use App\Domain\Documents\Services\PrivateFileVault;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;

class VaultKey extends Command
{
    protected $signature = 'vault:key {--if-missing} {--rotate : Preserve old keys and add a new active key}';

    protected $description = 'Create or rotate the independent document keyring without printing secrets';

    public function handle(): int
    {
        if (config('vault.key')) {
            $this->error('DOCUMENT_ENCRYPTION_KEY overrides the file. Manage this key through your secret manager.');

            return self::FAILURE;
        }
        $path = config('vault.key_file');
        if (is_file($path) && $this->option('if-missing')) {
            app(PrivateFileVault::class)->encrypter();

            return self::SUCCESS;
        }
        if (is_file($path) && ! $this->option('rotate')) {
            $this->error('Keyring already exists. It has not been overwritten.');

            return self::FAILURE;
        }
        if ($this->option('rotate') && ! app()->isDownForMaintenance()) {
            $this->error('Put the application in maintenance mode and back up the keyring before rotation.');

            return self::FAILURE;
        }
        $keys = is_file($path) ? json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR) : [];
        array_unshift($keys, 'base64:'.base64_encode(Encrypter::generateKey('aes-256-gcm')));
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        $temporary = tempnam(dirname($path), '.key-');
        try {
            chmod($temporary, 0600);
            file_put_contents($temporary, json_encode($keys, JSON_THROW_ON_ERROR), LOCK_EX);
            if (! rename($temporary, $path)) {
                throw new \RuntimeException('Could not replace keyring');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
        $this->info('Keyring ready. Back it up separately, encrypted and outside this server. Losing it means losing access to documents.');

        return self::SUCCESS;
    }
}
