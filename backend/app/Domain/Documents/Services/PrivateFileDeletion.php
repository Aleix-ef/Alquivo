<?php

namespace App\Domain\Documents\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PrivateFileDeletion
{
    // Call in the same transaction as the account/document deletion.
    public function schedule(string $path, bool $directory = false): int
    {
        if (! preg_match('#^portfolios/[1-9][0-9]*(/[^.][^\\\\]*)?$#', $path) || str_contains($path, '..')) {
            throw new \InvalidArgumentException('Invalid cleanup path');
        }

        return DB::table('private_file_deletions')->insertGetId(['path' => $path, 'directory' => $directory, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function process(int $id): bool
    {
        $item = DB::table('private_file_deletions')->find($id);
        if (! $item) {
            return true;
        }
        try {
            $disk = Storage::disk('local');
            $success = $item->directory
                ? (! $disk->directoryExists($item->path) || $disk->deleteDirectory($item->path))
                : (! $disk->exists($item->path) || $disk->delete($item->path));
            if (! $success) {
                throw new \RuntimeException('Cleanup failed');
            }
            DB::table('private_file_deletions')->where('id', $id)->delete();

            return true;
        } catch (\Throwable) {
            Log::warning('Private file cleanup pending', ['cleanup_id' => $id]);

            return false;
        }
    }
}
