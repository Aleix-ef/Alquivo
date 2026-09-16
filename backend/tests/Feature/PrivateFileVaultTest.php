<?php

namespace Tests\Feature;

use App\Domain\Documents\Services\PrivateFileVault;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFileVaultTest extends TestCase
{
    public function test_file_is_encrypted_and_roundtrips_without_plaintext_on_disk(): void
    {
        Storage::fake('local');
        $vault = app(PrivateFileVault::class);
        $key = $vault->store(UploadedFile::fake()->createWithContent('invoice.pdf', 'private-content'), 'portfolios/1/documents');
        $this->assertStringNotContainsString('private-content', Storage::disk('local')->get($key));
        $this->assertSame('private-content', $vault->read($key));
    }

    public function test_encrypted_content_cannot_be_moved_to_another_portfolio_path(): void
    {
        $vault = app(PrivateFileVault::class);
        $bytes = $vault->encrypt('portfolios/1/file', 'sensitive');
        $this->expectException(\RuntimeException::class);
        $vault->decrypt('portfolios/2/file', $bytes);
    }

    public function test_wrong_key_fails_closed(): void
    {
        $vault = app(PrivateFileVault::class);
        $payload = $vault->encrypt('portfolios/1/file', 'private');
        config(['vault.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->expectException(DecryptException::class);
        $vault->decrypt('portfolios/1/file', $payload);
    }

    public function test_modified_ciphertext_fails_authentication(): void
    {
        $vault = app(PrivateFileVault::class);
        $payload = $vault->encrypt('portfolios/1/file', 'private');
        $this->expectException(DecryptException::class);
        $vault->decrypt('portfolios/1/file', substr($payload, 0, -8).'INVALID!');
    }

    public function test_plaintext_is_never_silently_served(): void
    {
        $this->expectException(\RuntimeException::class);
        app(PrivateFileVault::class)->decrypt('portfolios/1/file', 'private');
    }
}
