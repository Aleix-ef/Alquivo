<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional. A retry after interruption must preserve completed rows.
        $needsContent = ! Schema::hasColumn('ai_messages', 'content_encrypted');
        $needsMetadata = ! Schema::hasColumn('ai_messages', 'metadata_encrypted');
        if ($needsContent || $needsMetadata) {
            Schema::table('ai_messages', function (Blueprint $table) use ($needsContent, $needsMetadata) {
                // Encryption expansion can exceed MySQL TEXT even for an allowed 12k-character reply.
                if ($needsContent) {
                    $table->longText('content_encrypted')->nullable();
                }
                if ($needsMetadata) {
                    $table->longText('metadata_encrypted')->nullable();
                }
            });
        }

        // Query builder intentionally bypasses the new model accessors; update each row atomically.
        // Deploy in maintenance mode so an old application process cannot introduce new plaintext writes.
        DB::table('ai_messages')->select('id')->orderBy('id')->chunkById(200, function ($messages) {
            foreach ($messages as $message) {
                DB::transaction(function () use ($message) {
                    $current = DB::table('ai_messages')->where('id', $message->id)->lockForUpdate()->first();
                    if ($current === null) {
                        return;
                    }
                    DB::table('ai_messages')->where('id', $current->id)->update([
                        'content_encrypted' => $current->content_encrypted ?? Crypt::encryptString($current->content),
                        'metadata_encrypted' => $current->metadata_encrypted ?? ($current->metadata === null ? null : Crypt::encryptString($current->metadata)),
                        'content' => '', // Preserve the existing NOT NULL constraint.
                        'metadata' => null,
                    ]);
                });
            }
        });
    }

    public function down(): void
    {
        // Rollback is data-preserving, but deliberately restores plaintext for the previous code version.
        // Decrypt first; if a key is missing/ciphertext is corrupt, fail without dropping encrypted data.
        DB::table('ai_messages')->select(['id', 'content_encrypted', 'metadata_encrypted'])->orderBy('id')->chunkById(200, function ($messages) {
            foreach ($messages as $message) {
                $restored = [];
                if ($message->content_encrypted !== null) {
                    $restored['content'] = Crypt::decryptString($message->content_encrypted);
                }
                if ($message->metadata_encrypted !== null) {
                    $restored['metadata'] = Crypt::decryptString($message->metadata_encrypted);
                }
                if ($restored !== []) {
                    DB::table('ai_messages')->where('id', $message->id)->update($restored);
                }
            }
        });

        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn(['content_encrypted', 'metadata_encrypted']);
        });
    }
};
