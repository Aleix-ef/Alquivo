<?php

namespace Tests\Feature;

use App\Domain\Assistant\Services\AssistantLeasingQueries;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Services\RentChargeService;
use App\Domain\Leasing\Actions\CreateLease;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LeasePaymentWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->travelTo(Carbon::parse('2026-10-05 18:00:00'));
    }

    private function fixture(): array
    {
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Synthetic window']);
        $portfolio->members()->attach($user, ['role' => 'owner']);
        $property = $portfolio->properties()->create(['name' => 'Synthetic home', 'type' => 'housing', 'address_line' => 'Ficticia']);
        $contact = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'Synthetic tenant', 'kind' => 'person']);
        $input = ['property_id' => $property->id, 'contact_ids' => [$contact->id], 'status' => 'active',
            'start_date' => '2026-10-01', 'monthly_rent' => '550.35', 'payment_day_start' => 1, 'payment_day' => 5];

        return compact('user', 'portfolio', 'property', 'contact', 'input');
    }

    public function test_window_is_inclusive_and_due_at_the_last_day(): void
    {
        $f = $this->fixture();
        $response = $this->actingAs($f['user'])->postJson('/api/v1/leases', $f['input'])->assertCreated()
            ->assertJsonPath('payment_day_start', 1)->assertJsonPath('payment_day', 5)
            ->assertJsonPath('charges.0.status', 'pending');
        $lease = Lease::findOrFail($response->json('id'));
        $charge = $lease->charges()->sole();
        $this->assertSame('2026-10-05', $charge->due_date->toDateString());
        $this->assertSame('550.35', $charge->amount);
        $this->assertSame(0, app(RentChargeService::class)->refreshOverdueStatuses());
        $this->travel(1)->days();
        $this->assertSame(1, app(RentChargeService::class)->refreshOverdueStatuses());
        $this->assertSame('overdue', $charge->fresh()->status);
        $this->artisan('rent:generate')->assertSuccessful();
        $this->assertSame(1, $lease->charges()->count());
    }

    public function test_legacy_single_day_and_canonical_creation_payload_stay_compatible(): void
    {
        $f = $this->fixture();
        unset($f['input']['payment_day_start']);
        $data = app(CreateLease::class)->validatedData($f['portfolio'], $f['user'], $f['input']);
        $this->assertArrayNotHasKey('payment_day_start', $data);
        $id = $this->actingAs($f['user'])->postJson('/api/v1/leases', $f['input'])->assertCreated()
            ->assertJsonPath('payment_day_start', 5)->assertJsonPath('payment_day', 5)->json('id');
        $this->assertNull(Lease::findOrFail($id)->getRawOriginal('payment_day_start'));
        $this->getJson('/api/v1/leases/'.$id)->assertOk()->assertJsonPath('payment_day_start', 5);
    }

    public function test_additive_migration_preserves_existing_contract_and_charge_values(): void
    {
        $f = $this->fixture();
        unset($f['input']['payment_day_start']);
        $id = $this->actingAs($f['user'])->postJson('/api/v1/leases', $f['input'])->assertCreated()->json('id');
        Schema::table('leases', fn (Blueprint $table) => $table->dropColumn('payment_day_start'));
        $before = (array) DB::table('leases')->where('id', $id)->first();
        $charge = RentCharge::sole()->getRawOriginal();
        $migration = require database_path('migrations/2026_10_23_000000_add_lease_payment_window.php');
        $migration->up();
        $after = (array) DB::table('leases')->where('id', $id)->first();
        $this->assertNull($after['payment_day_start']);
        unset($after['payment_day_start']);
        $this->assertSame($before, $after);
        $this->assertSame(5, Lease::findOrFail($id)->payment_day_start);
        $this->assertSame($charge, RentCharge::sole()->getRawOriginal());
    }

    public static function invalidWindows(): array
    {
        return [
            'reversed' => [8, 5, 'payment_day_start'], 'start zero' => [0, 5, 'payment_day_start'],
            'end zero' => [1, 0, 'payment_day'], 'start too high' => [32, 32, 'payment_day_start'],
            'end too high' => [1, 32, 'payment_day'], 'fraction start' => [1.5, 5, 'payment_day_start'],
            'fraction end' => [1, 5.5, 'payment_day'], 'null start' => [null, 5, 'payment_day_start'],
        ];
    }

    #[DataProvider('invalidWindows')]
    public function test_invalid_windows_never_create_a_lease_or_contacts(mixed $start, mixed $end, string $field): void
    {
        $f = $this->fixture();
        $this->actingAs($f['user'])->postJson('/api/v1/leases', [...$f['input'],
            'payment_day_start' => $start, 'payment_day' => $end,
            'new_contacts' => [['name' => 'Must not be created']],
        ])->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('leases', 0);
        $this->assertDatabaseCount('rent_charges', 0);
        $this->assertDatabaseCount('contacts', 1);
    }

    public function test_edit_keeps_existing_charge_and_uses_window_for_future_months(): void
    {
        $f = $this->fixture();
        $id = $this->actingAs($f['user'])->postJson('/api/v1/leases', $f['input'])->assertCreated()->json('id');
        $before = RentCharge::sole()->getRawOriginal();
        $this->putJson('/api/v1/leases/'.$id, ['payment_day_start' => 2, 'payment_day' => 10])->assertOk()
            ->assertJsonPath('payment_day_start', 2)->assertJsonPath('payment_day', 10);
        $this->assertSame($before, RentCharge::sole()->getRawOriginal());
        $lease = Lease::findOrFail($id);
        $charge = app(RentChargeService::class)->ensureChargeForMonth($lease, Carbon::parse('2026-11-01'));
        $this->assertSame('2026-11-10', $charge->due_date->toDateString());
        $this->putJson('/api/v1/leases/'.$id, ['payment_day_start' => 12])->assertUnprocessable();
        $this->assertSame(2, $lease->fresh()->payment_day_start);
        $this->putJson('/api/v1/leases/'.$id, ['notes' => 'Unrelated update'])->assertOk()
            ->assertJsonPath('payment_day_start', 2)->assertJsonPath('payment_day', 10);
        // Old clients editing only payment_day retain their single-day semantics.
        $this->putJson('/api/v1/leases/'.$id, ['payment_day' => 3])->assertOk()
            ->assertJsonPath('payment_day_start', 3)->assertJsonPath('payment_day', 3);
    }

    public function test_renewal_accepts_window_without_changing_original_contract(): void
    {
        $f = $this->fixture();
        $id = $this->actingAs($f['user'])->postJson('/api/v1/leases', $f['input'])->assertCreated()->json('id');
        $this->postJson('/api/v1/leases/'.$id.'/renew', ['start_date' => '2027-10-01', 'monthly_rent' => '600.25',
            'payment_day_start' => 2, 'payment_day' => 7,
        ])->assertCreated()->assertJsonPath('status', 'draft')
            ->assertJsonPath('payment_day_start', 2)->assertJsonPath('payment_day', 7);
        $this->assertSame(1, Lease::findOrFail($id)->payment_day_start);
        $this->assertSame(1, RentCharge::count());
    }

    public function test_short_and_leap_months_clamp_deadline_without_duplicate_charges(): void
    {
        $f = $this->fixture();
        $id = $this->actingAs($f['user'])->postJson('/api/v1/leases', [...$f['input'],
            'start_date' => '2024-01-01', 'payment_day_start' => 30, 'payment_day' => 31,
        ])->assertCreated()->json('id');
        $lease = Lease::findOrFail($id);
        foreach (['2024-02-01' => '2024-02-29', '2025-02-01' => '2025-02-28', '2026-04-01' => '2026-04-30'] as $month => $expected) {
            $charge = app(RentChargeService::class)->ensureChargeForMonth($lease, Carbon::parse($month));
            $same = app(RentChargeService::class)->ensureChargeForMonth($lease, Carbon::parse($month));
            $this->assertSame($expected, $charge->due_date->toDateString());
            $this->assertSame($charge->id, $same->id);
        }
    }

    public function test_other_portfolio_cannot_change_read_or_renew_window(): void
    {
        $f = $this->fixture();
        $other = $this->fixture();
        $id = $this->actingAs($f['user'])->postJson('/api/v1/leases', $f['input'])->assertCreated()->json('id');
        $this->actingAs($other['user'])->getJson('/api/v1/leases/'.$id)->assertNotFound();
        $this->putJson('/api/v1/leases/'.$id, ['payment_day_start' => 2, 'payment_day' => 9])->assertNotFound();
        $this->postJson('/api/v1/leases/'.$id.'/renew', ['start_date' => '2027-01-01', 'monthly_rent' => 100,
            'payment_day_start' => 2, 'payment_day' => 9])->assertNotFound();
        $this->assertSame(1, Lease::findOrFail($id)->payment_day_start);
    }

    public function test_export_and_authorized_assistant_details_include_both_days(): void
    {
        $f = $this->fixture();
        $id = $this->actingAs($f['user'])->postJson('/api/v1/leases', $f['input'])->assertCreated()->json('id');
        $csv = $this->getJson('/api/v1/exports/leases')->assertOk()->streamedContent();
        $this->assertStringContainsString('Cobro desde el día', $csv);
        $this->assertStringContainsString('Cobro hasta el día', $csv);
        $result = app(AssistantLeasingQueries::class)->execute($f['portfolio'], 'get_lease_details', ['lease_id' => $id]);
        $this->assertSame(1, $result['lease']['payment_day_start']);
        $this->assertSame(5, $result['lease']['payment_day']);
        $this->assertStringContainsString('ambos incluidos', $result['lease']['payment_window_basis']);
        Http::assertNothingSent();
    }
}
