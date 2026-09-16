<?php

namespace Tests\Feature;

use App\Domain\Documents\Models\Document;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Fiscality\Models\PropertyTaxRecord;
use App\Domain\Fiscality\Models\TaxReportSnapshot;
use App\Domain\Fiscality\Models\TaxYear;
use App\Domain\Fiscality\Services\TaxReportRenderer;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FiscalFixture;
use Tests\TestCase;

class FiscalityApiTest extends TestCase
{
    use RefreshDatabase;

    private function context(string $plan = 'founder'): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Cartera fiscal', 'plan' => $plan, 'currency' => 'EUR']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Piso de prueba', 'type' => 'housing', 'address_line' => 'Calle Prueba 1', 'city' => 'Valencia', 'country_code' => 'ES', 'purchase_date' => '2020-01-01']);

        return [$user, $portfolio, $property];
    }

    private function prepare(User $user, $property): void
    {
        $this->actingAs($user)->putJson('/api/v1/fiscality/2025/profile', ['revision' => 0, 'name' => 'Titular de prueba', 'regime' => 'common'])->assertOk();
        $this->putJson('/api/v1/fiscality/2025/properties/'.$property->id, ['revision' => 0, 'inputs' => FiscalFixture::inputs()])->assertOk();
    }

    public function test_premium_is_enforced_on_writes_and_trial_has_access(): void
    {
        [$user, $portfolio, $property] = $this->context('free');
        $this->actingAs($user)->getJson('/api/v1/fiscality')->assertOk()->assertJsonPath('access', false)->assertJsonPath('dossier', null);
        $this->putJson('/api/v1/fiscality/2025/properties/'.$property->id, ['revision' => 0, 'inputs' => FiscalFixture::inputs()])->assertForbidden();
        $this->postJson('/api/v1/fiscality/2025/reports')->assertForbidden();
        $portfolio->update(['trial_ends_at' => now()->addDay()]);
        $this->prepare($user, $property);
        $this->getJson('/api/v1/fiscality')->assertOk()->assertJsonPath('access', true)->assertJsonPath('dossier.totals.reduced_net', 300000);
        $portfolio->update(['trial_ends_at' => now()->subSecond()]);
        $this->postJson('/api/v1/fiscality/2025/reports')->assertForbidden();
    }

    public function test_authorization_covers_properties_documents_and_downloads(): void
    {
        [$user, , $property] = $this->context();
        [$other, $otherPortfolio, $otherProperty] = $this->context();
        $this->prepare($other, $otherProperty);
        $id = $this->postJson('/api/v1/fiscality/2025/reports')->assertCreated()->json('id');
        $this->actingAs($user)->putJson('/api/v1/fiscality/2025/properties/'.$otherProperty->id, ['revision' => 0, 'inputs' => FiscalFixture::inputs()])->assertNotFound();
        $this->getJson('/api/v1/fiscality/reports/'.$id.'/pdf')->assertNotFound();
        $this->getJson('/api/v1/fiscality/reports/'.$id.'/csv')->assertNotFound();
        $this->postJson('/api/v1/fiscality/2025/reports', ['property_id' => $otherProperty->id])->assertUnprocessable();
        $document = Document::create(['portfolio_id' => $otherPortfolio->id, 'property_id' => $otherProperty->id, 'name' => 'Privado', 'category' => 'invoice', 'storage_key' => 'private.pdf', 'original_filename' => 'private.pdf', 'mime_type' => 'application/pdf', 'size' => 1, 'uploaded_by' => $other->id]);
        $input = FiscalFixture::inputs();
        $input['expenses'][0]['document_id'] = $document->id;
        $this->putJson('/api/v1/fiscality/2025/properties/'.$property->id, ['revision' => 0, 'inputs' => $input])->assertUnprocessable()->assertJsonValidationErrors('inputs.expenses.0.document_id');
    }

    public function test_missing_data_is_partial_and_profile_changes_recompute_all_properties(): void
    {
        [$user, $portfolio, $property] = $this->context();
        $this->prepare($user, $property);
        $portfolio->properties()->create(['name' => 'Sin datos fiscales', 'type' => 'housing', 'address_line' => 'Calle 2']);
        $this->getJson('/api/v1/fiscality')->assertOk()->assertJsonPath('dossier.partial', true)->assertJsonPath('dossier.calculated_count', 1)->assertJsonPath('dossier.totals.income', 1200000);
        $this->putJson('/api/v1/fiscality/2025/profile', ['revision' => 1, 'name' => 'Titular', 'regime' => 'foral'])->assertOk();
        $this->getJson('/api/v1/fiscality')->assertJsonPath('dossier.calculated_count', 0)->assertJsonPath('dossier.totals.income', null);
    }

    public function test_charges_and_their_partial_payments_are_not_double_counted(): void
    {
        [$user, $portfolio, $property] = $this->context();
        $lease = Lease::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'start_date' => '2024-01-01', 'monthly_rent' => '1000', 'status' => 'active']);
        $charge = RentCharge::create(['portfolio_id' => $portfolio->id, 'lease_id' => $lease->id, 'period' => '2025-01', 'due_date' => '2025-01-05', 'amount' => '1000', 'paid_amount' => '400', 'status' => 'partial']);
        Transaction::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'rent_charge_id' => $charge->id, 'direction' => 'income', 'category' => 'rent', 'description' => 'Pago parcial', 'amount' => '400', 'transaction_date' => '2025-01-05', 'status' => 'paid']);
        $this->actingAs($user)->getJson('/api/v1/fiscality')->assertOk()->assertJsonPath('dossier.properties.0.source.charge_income_cents', 100000)->assertJsonPath('dossier.properties.0.source.paid_income_cents', 40000)->assertJsonPath('dossier.totals.income', null);
    }

    public function test_invalid_periods_unknown_categories_and_stale_revisions_are_rejected(): void
    {
        [$user, , $property] = $this->context();
        $this->prepare($user, $property);
        $this->putJson('/api/v1/fiscality/2025/properties/'.$property->id, ['revision' => 0, 'inputs' => FiscalFixture::inputs()])->assertConflict();
        $input = FiscalFixture::inputs();
        $input['periods'][] = ['start' => '2025-06-01', 'end' => '2025-06-10', 'use' => 'available'];
        $this->putJson('/api/v1/fiscality/2025/properties/'.$property->id, ['revision' => 1, 'inputs' => $input])->assertUnprocessable()->assertJsonValidationErrors('inputs.periods');
        $input = FiscalFixture::inputs();
        $input['expenses'][0]['category'] = 'mortgage_principal';
        $this->putJson('/api/v1/fiscality/2025/properties/'.$property->id, ['revision' => 1, 'inputs' => $input])->assertUnprocessable();
        $this->getJson('/api/v1/fiscality?year=2026')->assertUnprocessable();
        $this->postJson('/api/v1/fiscality/2026/reports')->assertUnprocessable();
    }

    public function test_encrypted_immutable_snapshots_are_reused_and_survive_downgrade(): void
    {
        [$user, $portfolio, $property] = $this->context();
        $this->prepare($user, $property);
        $id = $this->postJson('/api/v1/fiscality/2025/reports')->assertCreated()->json('id');
        $this->postJson('/api/v1/fiscality/2025/reports')->assertCreated()->assertJsonPath('id', $id);
        $original = $this->get('/api/v1/fiscality/reports/'.$id.'/csv')->assertOk()->getContent();
        $this->assertStringNotContainsString('Titular de prueba', DB::table('tax_years')->value('profile'));
        $this->assertStringNotContainsString('Ejemplo ficticio', DB::table('property_tax_records')->value('inputs'));
        $this->assertStringNotContainsString('Piso de prueba', DB::table('tax_report_snapshots')->value('payload'));
        $this->putJson('/api/v1/fiscality/2025/properties/'.$property->id, ['revision' => 1, 'inputs' => FiscalFixture::inputs(['income' => '20000'])])->assertOk();
        $newId = $this->postJson('/api/v1/fiscality/2025/reports')->assertCreated()->json('id');
        $this->assertNotSame($id, $newId);
        $portfolio->update(['plan' => 'free']);
        $this->assertSame($original, $this->get('/api/v1/fiscality/reports/'.$id.'/csv')->assertOk()->getContent());
        $pdf = $this->get('/api/v1/fiscality/reports/'.$id.'/pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $this->assertStringContainsString('no-store', $pdf->headers->get('Cache-Control'));
        $this->postJson('/api/v1/fiscality/2025/reports')->assertForbidden();
    }

    public function test_template_escapes_user_html_and_csv_protects_formula_cells(): void
    {
        [$user, , $property] = $this->context();
        $property->update(['name' => ' =HYPERLINK("https://bad.invalid")']);
        $this->prepare($user, $property);
        $this->putJson('/api/v1/fiscality/2025/properties/'.$property->id, ['revision' => 1, 'inputs' => FiscalFixture::inputs(['notes' => '<img src="file:///etc/passwd"><script>alert(1)</script>'])])->assertOk();
        $id = $this->postJson('/api/v1/fiscality/2025/reports')->assertCreated()->json('id');
        $payload = [...TaxReportSnapshot::find($id)->payload, 'report_id' => $id, 'generated_at' => '10/09/2026'];
        $html = view('fiscality.dossier-v1', ['report' => $payload])->render();
        $this->assertStringNotContainsString('<img src="file:///etc/passwd">', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString("' =HYPERLINK", app(TaxReportRenderer::class)->csv($payload));
    }

    public function test_deleted_account_portfolio_leaves_no_fiscal_records(): void
    {
        [$user, $portfolio, $property] = $this->context();
        $this->prepare($user, $property);
        $this->postJson('/api/v1/fiscality/2025/reports')->assertCreated();
        $portfolio->delete();
        $this->assertSame(0, TaxYear::count());
        $this->assertSame(0, PropertyTaxRecord::count());
        $this->assertSame(0, TaxReportSnapshot::count());
    }
}
