<?php

namespace Tests\Feature;

use App\Domain\Attention\Models\Reminder;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CalendarApiTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): array
    {
        Http::preventStrayRequests();
        $user = User::factory()->create();
        $portfolio = Portfolio::create(['name' => 'Synthetic calendar']);
        $portfolio->members()->attach($user, ['role' => 'owner']);

        return [$user, $portfolio];
    }

    public function test_visible_month_range_includes_neighbouring_days_but_not_other_portfolios(): void
    {
        [$user, $portfolio] = $this->owner();
        [, $other] = $this->owner();
        foreach (['2026-09-28', '2026-10-22', '2026-11-08', '2026-11-10'] as $date) {
            Reminder::create(['portfolio_id' => $portfolio->id, 'title' => 'Synthetic '.$date, 'starts_at' => $date.' 09:00:00']);
        }
        $foreign = Reminder::create(['portfolio_id' => $other->id, 'title' => 'Foreign synthetic reminder', 'starts_at' => '2026-10-22 09:00:00']);
        $this->actingAs($user)->getJson('/api/v1/calendar?'.http_build_query([
            'from' => '2026-09-27 00:00:00', 'to' => '2026-11-09 23:59:59',
        ]))->assertOk()->assertJsonCount(3, 'events')
            ->assertJsonPath('events.0.date', '2026-09-28')->assertJsonPath('events.2.date', '2026-11-08')
            ->assertJsonMissing(['id' => 'reminder-'.$foreign->id]);
        Http::assertNothingSent();
    }

    public function test_reminder_utc_timestamp_round_trips_on_create_and_edit(): void
    {
        [$user] = $this->owner();
        $id = $this->actingAs($user)->postJson('/api/v1/reminders', [
            'title' => 'Synthetic UTC boundary', 'starts_at' => '2026-10-31T23:30:00.000Z',
        ])->assertCreated()->json('id');
        $url = '/api/v1/calendar?'.http_build_query(['from' => '2026-10-25 00:00:00', 'to' => '2026-11-09 23:59:59']);
        $this->getJson($url)->assertOk()->assertJsonPath('events.0.starts_at', '2026-10-31T23:30:00+00:00');
        $this->putJson('/api/v1/reminders/'.$id, ['starts_at' => '2026-11-01T08:15:00.000Z'])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonPath('events.0.starts_at', '2026-11-01T08:15:00+00:00');
        $this->putJson('/api/v1/reminders/'.$id, ['completed' => true])->assertOk();
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'events');
        Http::assertNothingSent();
    }
}
