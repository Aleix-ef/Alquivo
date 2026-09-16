<?php

namespace Tests\Feature;

use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\PrivateFileVault;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VaultMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_conversion_rotation_and_old_key_recovery(): void
    {
        Storage::fake('local');
        $path = tempnam(sys_get_temp_dir(), 'alquivo-test-key-');
        file_put_contents($path, json_encode(['base64:'.base64_encode(random_bytes(32))]));
        config(['vault.key' => null, 'vault.key_file' => $path]);
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Test']);
        $key = 'portfolios/'.$portfolio->id.'/documents/legacy.pdf';
        $bytes = '%PDF-1.4 synthetic test';
        Storage::disk('local')->put($key, $bytes);
        Document::create(['portfolio_id' => $portfolio->id, 'uploaded_by' => $user->id, 'name' => 'Test', 'category' => 'invoice', 'storage_key' => $key, 'original_filename' => 'legacy.pdf', 'mime_type' => 'application/pdf', 'size' => strlen($bytes)]);
        try {
            $this->artisan('vault:files')->assertFailed();
            $this->artisan('vault:files --encrypt --backup-confirmed')->assertFailed();
            $this->assertSame($bytes, Storage::disk('local')->get($key));
            $this->artisan('down')->assertSuccessful();
            $this->artisan('vault:files --encrypt --backup-confirmed')->assertSuccessful();
            $payload = Storage::disk('local')->get($key);
            $this->assertStringNotContainsString($bytes, $payload);
            $this->assertSame($bytes, app(PrivateFileVault::class)->read($key));
            $this->artisan('vault:key --rotate')->assertSuccessful();
            $this->assertCount(2, json_decode(file_get_contents($path), true));
            $this->assertSame($bytes, app(PrivateFileVault::class)->decrypt($key, $payload));
            $this->artisan('vault:files --encrypt --backup-confirmed')->assertSuccessful();
            $this->artisan('vault:files')->assertSuccessful();
        } finally {
            $this->artisan('up');
            unlink($path);
        }
    }
}
