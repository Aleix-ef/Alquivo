<?php

// Standalone PostgreSQL concurrency check. Never run against an application database.
use App\Domain\Assistant\Models\AiConversation;
use App\Domain\Assistant\Models\AiRun;
use App\Domain\Assistant\Services\ActionProposalService;
use App\Domain\Assistant\Services\AssistantLeasingQueries;
use App\Domain\Finance\Models\RentCharge;
use App\Domain\Finance\Models\Transaction;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Leasing\Models\Lease;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$expected = getenv('AI_CONCURRENCY_TEST_DATABASE');
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! is_string($expected) || ! preg_match('/^alquivo_ai_test_[a-z0-9_]+$/D', $expected)
    || config('database.connections.pgsql.database') !== $expected
    || DB::selectOne('select current_database() as name')->name !== $expected
    || ! function_exists('pcntl_fork') || ! function_exists('posix_kill')) {
    fwrite(STDERR, "Refusing: requires testing, PostgreSQL, pcntl/posix and an explicitly named isolated test database.\n");
    exit(2);
}
config(['beta.program_enabled' => false, 'beta.assistant_validated' => true, 'assistant.enabled' => true, 'ai.actions.enabled' => true]);
Http::preventStrayRequests();

/** Read only one small line, within a deadline shared by all workers. */
function workerLine($socket, float $deadline): string
{
    $remaining = $deadline - microtime(true);
    if ($remaining <= 0) {
        throw new RuntimeException('Worker deadline elapsed.');
    }
    $seconds = (int) $remaining;
    stream_set_timeout($socket, $seconds, (int) (($remaining - $seconds) * 1000000));
    $line = fgets($socket, 8192);
    if ($line === false || ! str_ends_with($line, "\n") || stream_get_meta_data($socket)['timed_out']) {
        throw new RuntimeException('Worker channel closed or timed out.');
    }

    return trim($line);
}

/** Two separate PostgreSQL connections reach a barrier before either confirms. */
function confirmTogether(int $portfolioId, int $userId, array $proposalIds): array
{
    if (count($proposalIds) !== 2) {
        throw new InvalidArgumentException('The concurrency check requires exactly two workers.');
    }
    // No inherited PDO sockets. The parent reconnects only after both children exit.
    DB::disconnect();
    $children = [];
    $deadline = microtime(true) + 30;
    try {
        foreach ($proposalIds as $proposalId) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            if ($pair === false) {
                throw new RuntimeException('Could not start isolated worker channel.');
            }
            $pid = pcntl_fork();
            if ($pid === -1) {
                fclose($pair[0]);
                fclose($pair[1]);
                throw new RuntimeException('Could not start isolated workers.');
            }
            if ($pid === 0) {
                fclose($pair[0]);
                foreach ($children as $previous) {
                    fclose($previous['socket']);
                }
                // Also bound a stalled connect/driver call, independently from the parent.
                pcntl_alarm(25);
                $writes = ['transactions' => 0, 'contacts' => 0, 'property_notes' => 0];
                try {
                    DB::purge();
                    DB::statement("SET lock_timeout = '10s'");
                    DB::statement("SET statement_timeout = '20s'");
                    DB::listen(function (QueryExecuted $query) use (&$writes) {
                        if (str_starts_with($query->sql, 'insert into "transactions"')) {
                            $writes['transactions']++;
                        } elseif (str_starts_with($query->sql, 'update "contacts" set ') && str_contains($query->sql, '"phone" =')) {
                            $writes['contacts']++;
                        } elseif (str_starts_with($query->sql, 'update "properties" set ') && str_contains($query->sql, '"notes" =')) {
                            $writes['property_notes']++;
                        }
                    });
                    fwrite($pair[1], "ready\n");
                    if (workerLine($pair[1], $deadline) !== 'confirm') {
                        throw new RuntimeException('Worker barrier was not released.');
                    }
                    $receipt = app(ActionProposalService::class)->confirm(
                        Portfolio::findOrFail($portfolioId), User::findOrFail($userId), $proposalId, 1,
                    );
                    $output = ['ok' => true, 'result' => $receipt->result, 'writes' => $writes];
                    $exitCode = 0;
                } catch (Throwable $exception) {
                    $httpStatus = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : null;
                    $output = ['ok' => false, 'http_status' => $httpStatus, 'error' => get_class($exception), 'writes' => $writes];
                    // A stale-preview conflict is expected only in the distinct-proposals test.
                    $exitCode = $httpStatus === 409 ? 0 : 1;
                }
                fwrite($pair[1], json_encode($output, JSON_THROW_ON_ERROR)."\n");
                DB::disconnect();
                fclose($pair[1]);
                pcntl_alarm(0);
                exit($exitCode);
            }
            fclose($pair[1]);
            $children[] = ['pid' => $pid, 'socket' => $pair[0], 'reaped' => false];
        }
        foreach ($children as $child) {
            if (workerLine($child['socket'], $deadline) !== 'ready') {
                throw new RuntimeException('Worker did not reach barrier.');
            }
        }
        foreach ($children as $child) {
            fwrite($child['socket'], "confirm\n");
        }
        $receipts = [];
        foreach ($children as $child) {
            $receipts[] = json_decode(workerLine($child['socket'], $deadline), true, 512, JSON_THROW_ON_ERROR);
        }
        foreach (array_keys($children) as $index) {
            do {
                $waited = pcntl_waitpid($children[$index]['pid'], $status, WNOHANG);
                if ($waited === $children[$index]['pid']) {
                    $children[$index]['reaped'] = true;
                    if (! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                        throw new RuntimeException('Worker exited unsuccessfully: '.json_encode($receipts, JSON_THROW_ON_ERROR));
                    }
                    break;
                }
                if ($waited === -1 || microtime(true) >= $deadline) {
                    throw new RuntimeException('Could not reap worker within deadline.');
                }
                usleep(20000);
            } while (true);
        }

        return $receipts;
    } finally {
        // Even a failed barrier/read cannot leave a blocked child or unbounded wait.
        foreach ($children as $child) {
            if (is_resource($child['socket'])) {
                fclose($child['socket']);
            }
            if (! $child['reaped']) {
                posix_kill($child['pid'], SIGKILL);
                $cleanupDeadline = microtime(true) + 2;
                do {
                    $waited = pcntl_waitpid($child['pid'], $status, WNOHANG);
                    if ($waited !== 0) {
                        break;
                    }
                    usleep(20000);
                } while (microtime(true) < $cleanupDeadline);
            }
        }
        DB::purge();
    }
}

function executionAudits(string $runId): int
{
    return DB::table('ai_run_steps')->where('run_id', $runId)->where('kind', 'action')->where('status', 'executed')->count();
}

function fixtureRun(Portfolio $portfolio, User $user, AiConversation $conversation): AiRun
{
    return AiRun::create([
        'portfolio_id' => $portfolio->id, 'user_id' => $user->id, 'conversation_id' => $conversation->id,
        'client_request_id' => (string) Str::uuid(), 'request_hash' => hash('sha256', 'synthetic'),
        'plan' => 'founder', 'routing_version' => 'test', 'billing_month' => today()->startOfMonth(), 'status' => 'completed',
    ]);
}

function requireCheck(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$user = User::create([
    'name' => 'Concurrency fixture', 'email' => Str::uuid().'@example.test', 'password' => 'synthetic-test-only-password',
    'email_verified_at' => now(),
]);
$user->forceFill(['assistant_enabled_at' => now(), 'assistant_notice_version' => config('assistant.notice_version')])->save();
$portfolio = Portfolio::create(['name' => 'Isolated concurrency fixture', 'currency' => 'EUR', 'plan' => 'founder', 'trial_ends_at' => null]);
$portfolio->members()->attach($user, ['role' => 'owner']);
$property = $portfolio->properties()->create(['name' => 'San Nicolás', 'type' => 'housing', 'address_line' => 'Synthetic', 'notes' => 'Existing synthetic note.']);
$contact = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'María Synthetic', 'kind' => 'person', 'phone' => '600000001']);
$lease = Lease::create([
    'portfolio_id' => $portfolio->id, 'property_id' => $property->id, 'status' => 'active',
    'start_date' => today()->startOfMonth(), 'end_date' => today()->addYear(),
    'monthly_rent' => '900.00', 'payment_day' => 5,
]);
$lease->participants()->attach($contact, ['role' => 'tenant', 'is_primary' => true]);
$charge = RentCharge::create([
    'portfolio_id' => $portfolio->id, 'lease_id' => $lease->id, 'period' => today()->format('Y-m'),
    'due_date' => today(), 'amount' => '900.00', 'paid_amount' => '0.00', 'status' => 'pending',
]);
$conversation = AiConversation::create(['user_id' => $user->id, 'portfolio_id' => $portfolio->id]);
$results = [];
try {
    $cases = [
        'expense' => ['method' => 'proposeExpense', 'writes' => 'transactions', 'input' => [
            'property_id' => $property->id, 'amount' => '84.00', 'category' => 'maintenance', 'description' => 'Synthetic plumbing',
            'transaction_date' => today()->toDateString(), 'status' => 'pending',
        ]],
        'contact_phone' => ['method' => 'proposeContactPhone', 'writes' => 'contacts', 'input' => [
            'contact_id' => $contact->id, 'phone' => '+34 612 345 678',
        ]],
        'rent_payment' => ['method' => 'proposeRentPayment', 'writes' => 'transactions', 'input' => [
            'rent_charge_id' => $charge->id, 'amount' => '80.25', 'transaction_date' => today()->toDateString(), 'payment_method' => 'transfer',
        ]],
        'property_note' => ['method' => 'proposePropertyNote', 'writes' => 'property_notes', 'input' => [
            'property_id' => $property->id, 'note' => 'Append this synthetic note exactly once.',
        ]],
    ];
    foreach ($cases as $type => $case) {
        $run = fixtureRun($portfolio, $user, $conversation);
        $proposal = app(ActionProposalService::class)->{$case['method']}($portfolio, $user, $run, $case['input']);
        $receipts = confirmTogether($portfolio->id, $user->id, [$proposal->id, $proposal->id]);
        $audits = executionAudits($run->id);
        $writes = array_sum(array_map(fn ($receipt) => $receipt['writes'][$case['writes']] ?? 0, $receipts));
        requireCheck(($receipts[0]['ok'] ?? false) && ($receipts[1]['ok'] ?? false), $type.': both confirmations must succeed.');
        requireCheck($receipts[0]['result'] === $receipts[1]['result'], $type.': repeated confirmations returned different receipts.');
        requireCheck($audits === 1 && $writes === 1, $type.': confirmation must execute and audit exactly once.');
        $stateMatches = match ($type) {
            'expense' => Transaction::where('portfolio_id', $portfolio->id)->where('direction', 'expense')->count() === 1,
            'contact_phone' => $contact->fresh()->phone === '+34 612 345 678',
            'rent_payment' => $charge->transactions()->count() === 1 && $charge->fresh()->paid_amount === '80.25' && $charge->fresh()->status === 'partial',
            'property_note' => $property->fresh()->notes === "Existing synthetic note.\n\nAppend this synthetic note exactly once.",
        };
        requireCheck($stateMatches, $type.': final domain state was not the expected single mutation.');
        $results[$type] = ['passed' => true, 'workers' => 2, 'same_receipt' => true, 'domain_writes' => $writes, 'execution_audits' => $audits];
    }

    // Different proposals can target the same balance. The loser must refresh its stale preview.
    $secondCharge = RentCharge::create([
        'portfolio_id' => $portfolio->id, 'lease_id' => $lease->id, 'period' => today()->startOfMonth()->addMonth()->format('Y-m'),
        'due_date' => today()->startOfMonth()->addMonth(), 'amount' => '50.00', 'paid_amount' => '0.00', 'status' => 'pending',
    ]);
    $runs = [fixtureRun($portfolio, $user, $conversation), fixtureRun($portfolio, $user, $conversation)];
    $proposalIds = array_map(fn ($run) => app(ActionProposalService::class)->proposeRentPayment($portfolio, $user, $run, [
        'rent_charge_id' => $secondCharge->id, 'amount' => '50.00', 'transaction_date' => today()->toDateString(), 'payment_method' => null,
    ])->id, $runs);
    $receipts = confirmTogether($portfolio->id, $user->id, $proposalIds);
    $successful = count(array_filter($receipts, fn ($receipt) => $receipt['ok'] ?? false));
    $conflicts = count(array_filter($receipts, fn ($receipt) => ! ($receipt['ok'] ?? false) && ($receipt['http_status'] ?? null) === 409));
    $audits = array_sum(array_map(fn ($run) => executionAudits($run->id), $runs));
    $recorded = $secondCharge->transactions()->where('status', 'paid')->get()->reduce(fn (string $sum, $payment) => bcadd($sum, $payment->amount, 2), '0.00');
    requireCheck($successful === 1 && $conflicts === 1 && $audits === 1, 'Competing proposals must produce one success, one conflict and one execution audit.');
    requireCheck($secondCharge->transactions()->count() === 1 && $recorded === '50.00' && bccomp($recorded, $secondCharge->amount, 2) <= 0, 'Competing proposals overcharged the same rent balance.');
    $results['competing_rent_proposals'] = ['passed' => true, 'workers' => 2, 'successes' => $successful, 'conflicts' => $conflicts, 'payments_created' => 1, 'execution_audits' => $audits];

    // Exercise the correlated balance SQL on PostgreSQL, not just the SQLite feature suite.
    $queries = app(AssistantLeasingQueries::class);
    $contacts = $queries->execute($portfolio, 'search_contacts', ['query' => 'Maria Synthetic', 'property_id' => $property->id]);
    $charges = $queries->execute($portfolio, 'list_rent_charges', ['lease_id' => $lease->id, 'status' => 'all']);
    $pending = $queries->execute($portfolio, 'list_rent_charges', ['lease_id' => $lease->id, 'status' => 'pending']);
    $paid = $queries->execute($portfolio, 'list_rent_charges', ['lease_id' => $lease->id, 'status' => 'paid']);
    $leases = $queries->execute($portfolio, 'list_leases', ['property_id' => $property->id, 'status' => 'active']);
    $detail = $queries->execute($portfolio, 'get_lease_details', ['lease_id' => $lease->id]);
    requireCheck($contacts['count'] === 1 && $contacts['contacts'][0]['id'] === $contact->id && ! $contacts['needs_clarification'], 'PostgreSQL contact resolution failed.');
    requireCheck(! array_key_exists('phone', $contacts['contacts'][0]) && ! array_key_exists('email', $contacts['contacts'][0]), 'Contact projection exposed private contact fields.');
    requireCheck($charges['summary'] === ['count' => 2, 'overdue_count' => 0, 'amount' => '950.00', 'paid_amount' => '130.25', 'remaining_amount' => '819.75'], 'PostgreSQL rent summary is incorrect.');
    requireCheck($pending['count'] === 1 && $pending['charges'][0]['id'] === $charge->id && $paid['count'] === 1 && $paid['charges'][0]['id'] === $secondCharge->id, 'PostgreSQL paid/pending filters are incorrect.');
    requireCheck($leases['count'] === 1 && $detail['lease']['id'] === $lease->id && $detail['lease']['participant_count'] === 1 && $detail['rent_summary'] === $charges['summary'], 'PostgreSQL lease projections are incorrect.');
    requireCheck(Http::recorded()->isEmpty(), 'This test must not call any external provider.');
    $results['postgresql_read_queries'] = ['passed' => true, 'tools_checked' => 4, 'balance_filters_checked' => 3, 'network_calls' => 0];
    echo json_encode(['passed' => true, 'checks' => $results], JSON_THROW_ON_ERROR).PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    // Synthetic fixture only. Never dump query bindings, credentials or a connection string.
    fwrite(STDERR, json_encode(['passed' => false, 'checks' => $results, 'error' => get_class($exception), 'message' => $exception instanceof RuntimeException && ! $exception instanceof QueryException ? $exception->getMessage() : 'PostgreSQL fixture verification failed.'], JSON_THROW_ON_ERROR).PHP_EOL);
    exit(1);
}
