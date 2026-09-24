<?php

// Standalone numeric/query check. Never run against the application database.
use App\Domain\Assistant\Services\PortfolioAssistantTools;
use App\Domain\Attention\Services\PortfolioAttention;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$database = getenv('ATTENTION_TEST_DATABASE');
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! is_string($database) || ! preg_match('/^alquivo_ai_test_[a-z0-9_]+$/D', $database)
    || config('database.connections.pgsql.database') !== $database
    || DB::selectOne('select current_database() as name')->name !== $database) {
    fwrite(STDERR, "Refusing: an explicitly named isolated PostgreSQL test database is required.\n");
    exit(2);
}
Http::preventStrayRequests();
config(['assistant.enabled' => false, 'beta.assistant_validated' => false]);
$check = function ($condition, $message) {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
DB::beginTransaction();
try {
    $portfolio = Portfolio::create(['name' => 'Attention test', 'currency' => 'EUR']);
    $property = $portfolio->properties()->create(['name' => 'Synthetic', 'type' => 'housing', 'address_line' => 'No real address']);
    $lease = Lease::create(['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active', 'start_date' => '2020-01-01', 'end_date' => today()->addDays(60), 'monthly_rent' => '600.00']);
    $charge = $lease->charges()->create(['portfolio_id' => $portfolio->id, 'period' => today()->format('Y-m'), 'due_date' => today(), 'amount' => '600.00', 'paid_amount' => '600.00', 'status' => 'paid']);
    $payment = ['portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'lease_id' => $lease->id, 'rent_charge_id' => $charge->id, 'direction' => 'income', 'category' => 'rent', 'description' => 'Synthetic', 'transaction_date' => today(), 'status' => 'paid'];
    Transaction::create([...$payment, 'amount' => '0.10']);
    Transaction::create([...$payment, 'amount' => '0.20']);
    $service = app(PortfolioAttention::class);
    $s = $service->snapshot($portfolio);
    $check($s['total'] === 2 && $s['items'][0]['amount'] === '599.70', 'Exact numeric balance / lease horizon');
    $check($s['items'][0]['priority']['code'] === 'today', 'Today is not overdue');
    $other = Portfolio::create(['name' => 'Other']);
    $check($service->snapshot($other)['total'] === 0, 'Portfolio isolation');
    $tool = app(PortfolioAssistantTools::class)->execute($portfolio, 'get_attention_items', ['page' => 1, 'property_id' => null]);
    $check($tool['items'] === $s['items'], 'Same domain source for tool');
    Transaction::create([...$payment, 'amount' => '599.70']);
    $check($service->snapshot($portfolio)['counts']['rents'] === 0, 'Payment resolves item');
    $lease->update(['status' => 'cancelled']);
    $check($service->snapshot($portfolio)['total'] === 0, 'Cancelled contract resolves item');
    $check(Http::recorded()->isEmpty(), 'No provider calls');
    echo "PASS: PostgreSQL exact numeric balances, dates, isolation, shared tool and resolution; zero provider calls.\n";
} finally {
    DB::rollBack();
}
