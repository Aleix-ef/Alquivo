<?php

namespace App\Console\Commands;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\PrivateFileVault;
use App\Domain\Properties\Models\PropertyPhoto;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class VaultFiles extends Command
{
    protected $signature = 'vault:files {--encrypt : Convert legacy files or re-encrypt with the active key} {--backup-confirmed}';

    protected $description = 'Verify all referenced private files; optionally encrypt atomically during maintenance';

    public function handle(PrivateFileVault $vault): int
    {
        if ($this->option('encrypt') && (! $this->option('backup-confirmed') || ! app()->isDownForMaintenance())) {
            $this->error('Requires maintenance mode, stopped workers and a verified backup (--backup-confirmed).');

            return self::FAILURE;
        }
        $vault->encrypter();
        $count = 0;
        foreach ([Document::class, PropertyPhoto::class] as $model) {
            foreach ($model::select(['id', 'storage_key', 'size'])->cursor() as $file) {
                try {
                    $disk = Storage::disk('local');
                    $payload = $disk->get($file->storage_key);
                    $encrypted = str_starts_with($payload, PrivateFileVault::HEADER);
                    $bytes = $encrypted ? $vault->decrypt($file->storage_key, $payload) : $payload;
                    if (strlen($bytes) !== (int) $file->size) {
                        throw new \RuntimeException('Size mismatch');
                    }
                    if ($this->option('encrypt')) {
                        $new = $vault->encrypt($file->storage_key, $bytes);
                        if (! hash_equals(hash('sha256', $bytes), hash('sha256', $vault->decrypt($file->storage_key, $new)))) {
                            throw new \RuntimeException('Integrity mismatch');
                        }
                        $target = $disk->path($file->storage_key);
                        $temp = tempnam(dirname($target), '.vault-');
                        try {
                            chmod($temp, 0600);
                            if (file_put_contents($temp, $new, LOCK_EX) !== strlen($new)) {
                                throw new \RuntimeException('Write failed');
                            }
                            if (! rename($temp, $target)) {
                                throw new \RuntimeException('Replace failed');
                            }
                        } finally {
                            if (is_file($temp)) {
                                unlink($temp);
                            }
                        }
                    } elseif (! $encrypted) {
                        throw new \RuntimeException('Legacy plaintext detected');
                    }
                    $count++;
                } catch (\Throwable $error) {
                    $this->error('Verification failed for '.$model.' #'.$file->id.' ('.class_basename($error).'). No file content or key is logged.');

                    return self::FAILURE;
                }
            }
        }
        $this->info("Verified {$count} private files.");

        return self::SUCCESS;
    }
}
