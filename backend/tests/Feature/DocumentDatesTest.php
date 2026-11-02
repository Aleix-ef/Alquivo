<?php

namespace Tests\Feature;

use App\Domain\Documents\Models\Document;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DocumentDatesTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera sintética']);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        return $user;
    }

    private function document(User $user, array $attributes = []): Document
    {
        return Document::create([
            'portfolio_id' => $user->portfolio()->id,
            'name' => 'Documento sintético', 'category' => 'contract',
            'storage_key' => 'synthetic.pdf', 'original_filename' => 'synthetic.pdf',
            'mime_type' => 'application/pdf', 'size' => 10, 'uploaded_by' => $user->id,
            'issued_at' => '2026-01-01', 'expires_at' => '2026-12-31', ...$attributes,
        ]);
    }

    public static function categories(): array
    {
        return array_map(fn ($category) => [$category], ['contract', 'invoice', 'insurance', 'tax', 'certificate', 'other']);
    }

    #[DataProvider('categories')]
    public function test_all_categories_accept_uploads_without_dates(string $category): void
    {
        $this->actingAs($this->owner())->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('synthetic.pdf', 10, 'application/pdf'),
            'category' => $category,
        ])->assertCreated()->assertJsonPath('issued_at', null)->assertJsonPath('expires_at', null);
    }

    public function test_review_date_does_not_require_an_issuance_date(): void
    {
        $user = $this->owner();
        $created = $this->actingAs($user)->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('synthetic.pdf', 10, 'application/pdf'),
            'category' => 'insurance', 'expires_at' => '2026-12-31',
        ])->assertCreated()->assertJsonPath('issued_at', null);
        $this->assertStringStartsWith('2026-12-31', $created->json('expires_at'));

        $document = $this->document($user);
        $this->putJson("/api/v1/documents/{$document->id}", [
            'issued_at' => null, 'expires_at' => '2026-12-01',
        ])->assertOk()->assertJsonPath('issued_at', null);
        $this->assertSame('2026-12-01', $document->fresh()->expires_at->toDateString());
    }

    public function test_upload_rejects_reversed_dates_before_storing_any_file(): void
    {
        $this->actingAs($this->owner())->postJson('/api/v1/documents', [
            'file' => UploadedFile::fake()->create('synthetic.pdf', 10, 'application/pdf'),
            'category' => 'contract', 'issued_at' => '2026-12-31', 'expires_at' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');
        $this->assertDatabaseCount('documents', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_partial_updates_validate_the_effective_dates_already_stored(): void
    {
        $user = $this->owner();
        $document = $this->document($user);
        $this->actingAs($user)->putJson("/api/v1/documents/{$document->id}", [
            'expires_at' => '2025-12-31',
        ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');
        $this->putJson("/api/v1/documents/{$document->id}", [
            'issued_at' => '2027-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('expires_at');
        $this->assertSame('2026-01-01', $document->fresh()->issued_at->toDateString());
        $this->assertSame('2026-12-31', $document->fresh()->expires_at->toDateString());

        $this->putJson("/api/v1/documents/{$document->id}", [
            'expires_at' => '2026-11-30',
        ])->assertOk();
        $this->assertSame('2026-11-30', $document->fresh()->expires_at->toDateString());
    }

    public function test_category_changes_preserve_existing_dates_and_clearing_them_is_explicit(): void
    {
        $user = $this->owner();
        $document = $this->document($user);
        $this->actingAs($user)->putJson("/api/v1/documents/{$document->id}", [
            'category' => 'invoice',
        ])->assertOk();
        $this->assertSame('2026-01-01', $document->fresh()->issued_at->toDateString());
        $this->assertSame('2026-12-31', $document->fresh()->expires_at->toDateString());
        $this->putJson("/api/v1/documents/{$document->id}", [
            'issued_at' => null, 'expires_at' => null,
        ])->assertOk()->assertJsonPath('issued_at', null)->assertJsonPath('expires_at', null);
    }

    public function test_dates_can_be_equal_and_invalid_dates_are_rejected(): void
    {
        $user = $this->owner();
        $document = $this->document($user);
        $this->actingAs($user)->putJson("/api/v1/documents/{$document->id}", [
            'issued_at' => '2026-11-01', 'expires_at' => '2026-11-01',
        ])->assertOk();
        $this->putJson("/api/v1/documents/{$document->id}", [
            'issued_at' => '2026-02-30',
        ])->assertUnprocessable()->assertJsonValidationErrors('issued_at');
    }

    public function test_document_edits_stay_isolated_by_portfolio(): void
    {
        $owner = $this->owner();
        $document = $this->document($owner);
        $other = $this->owner();
        $this->actingAs($other)->putJson("/api/v1/documents/{$document->id}", [
            'issued_at' => null, 'expires_at' => null,
        ])->assertNotFound();
        $this->assertSame('2026-12-31', $document->fresh()->expires_at->toDateString());
    }
}
