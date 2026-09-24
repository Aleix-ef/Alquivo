<?php

// Only an isolated, explicitly named PostgreSQL test database. Never application data.
use App\Domain\Assistant\Documents\DocumentDemoFixtures;
use App\Domain\Assistant\Documents\DocumentExtractionService;
use App\Domain\Documents\Models\Document;
use App\Domain\Documents\Services\PrivateFileVault;
use App\Domain\Leasing\Models\Contact;
use App\Domain\Portfolio\Models\Portfolio;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$expected = getenv('AI_CONCURRENCY_TEST_DATABASE');
if (! $app->environment('testing') || config('database.default') !== 'pgsql'
    || ! is_string($expected) || ! preg_match('/^alquivo_ai_test_[a-z0-9_]+$/D', $expected)
    || config('database.connections.pgsql.database') !== $expected
    || DB::selectOne('select current_database() as name')->name !== $expected
    || ! function_exists('pcntl_fork')) {
    fwrite(STDERR, "Refusing: an isolated PostgreSQL testing database is required.\n");
    exit(2);
}
set_exception_handler(function (Throwable $exception): void {
    fwrite(STDERR, 'Document concurrency check FAILED: '.get_class($exception).PHP_EOL);
    exit(1);
});
Artisan::call('migrate', ['--force' => true]);
$app->instance('env', 'local'); // Exercise actual local-admin gate after database safety checks.
config(['assistant.enabled' => true, 'beta.assistant_validated' => false,
    'vault.key' => 'base64:'.base64_encode(str_repeat('k', 32)), 'security.uploads_scan' => false,
    'queue.default' => 'database', 'cache.default' => 'array']);
Storage::fake('local');
Http::preventStrayRequests();

foreach (['invoice', 'contract'] as $kind) {
    $user = User::forceCreate(['name' => 'Test', 'email' => $kind.'@synthetic.test', 'password' => 'synthetic-only',
        'role' => 'admin', 'email_verified_at' => now(), 'two_factor_confirmed_at' => now(),
        'document_ai_accepted_at' => now(), 'document_ai_notice_version' => config('ai_documents.notice_version')]);
    $portfolio = Portfolio::create(['name' => 'Synthetic', 'currency' => 'EUR', 'plan' => 'beta']);
    $portfolio->members()->attach($user, ['role' => 'owner']);
    $property = $portfolio->properties()->create(['name' => 'Piso', 'type' => 'housing', 'address_line' => 'Calle ficticia']);
    $contact = Contact::create(['portfolio_id' => $portfolio->id, 'name' => 'Ana Ejemplo', 'kind' => 'person']);
    $bytes = app(DocumentDemoFixtures::class)->pdf($kind);
    $key = 'test/'.$kind.'.enc';
    Storage::disk('local')->put($key, app(PrivateFileVault::class)->encrypt($key, $bytes));
    $document = Document::create(['portfolio_id' => $portfolio->id, 'uploaded_by' => $user->id,
        'name' => $kind, 'category' => $kind, 'original_filename' => 'test.pdf', 'storage_key' => $key,
        'size' => strlen($bytes), 'mime_type' => 'application/pdf']);
    $extraction = app(DocumentExtractionService::class)->start($user, $portfolio, $document, $kind);
    if ($extraction->status !== 'queued' || DB::table('jobs')->count() !== 1) {
        throw new RuntimeException('Expected asynchronous queue job');
    }
    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1, '--timeout' => 70]);
    $extraction->refresh();
    if ($extraction->status !== 'needs_review') {
        throw new RuntimeException('Worker did not create draft: '.$extraction->error_code);
    }
    if ($kind === 'contract') {
        $extraction = app(DocumentExtractionService::class)->mutate($user, $portfolio, $extraction->id, 'revise', [
            'revision' => 1, 'values' => [...$extraction->draft['values'], 'property_id' => $property->id, 'contact_ids' => [$contact->id]],
        ]);
    }
    DB::disconnect();
    $children = [];
    try {
        foreach ([1, 2] as $worker) {
            $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
            $pid = pcntl_fork();
            if ($pid < 0) {
                throw new RuntimeException('Fork failed');
            }
            if ($pid === 0) {
                fclose($pair[0]);
                foreach ($children as $child) {
                    fclose($child['socket']);
                }
                pcntl_alarm(25);
                try {
                    DB::purge();
                    DB::statement("SET lock_timeout = '10s'");
                    DB::statement("SET statement_timeout = '20s'");
                    stream_set_timeout($pair[1], 15);
                    fwrite($pair[1], "ready\n");
                    if (trim((string) fgets($pair[1])) !== 'go') {
                        throw new RuntimeException('Barrier timeout');
                    }
                    $result = app(DocumentExtractionService::class)->mutate(User::findOrFail($user->id), Portfolio::findOrFail($portfolio->id),
                        $extraction->id, 'confirm', ['revision' => $extraction->revision]);
                    fwrite($pair[1], json_encode(['ok' => true, 'receipt' => $result->receipt])."\n");
                    DB::disconnect();
                    exit(0);
                } catch (Throwable $e) {
                    fwrite($pair[1], json_encode(['ok' => false, 'error' => get_class($e)])."\n");
                    exit(1);
                }
            }
            fclose($pair[1]);
            stream_set_timeout($pair[0], 20);
            $children[] = ['pid' => $pid, 'socket' => $pair[0], 'reaped' => false];
        }
        foreach ($children as $child) {
            if (trim((string) fgets($child['socket'])) !== 'ready') {
                throw new RuntimeException('Worker not ready');
            }
        }
        foreach ($children as $child) {
            fwrite($child['socket'], "go\n");
        }
        $receipts = [];
        foreach ($children as $index => $child) {
            $result = json_decode((string) fgets($child['socket']), true, flags: JSON_THROW_ON_ERROR);
            pcntl_waitpid($child['pid'], $status);
            $children[$index]['reaped'] = true;
            if (! $result['ok'] || ! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
                throw new RuntimeException('Confirmation failed');
            }
            $receipts[] = $result['receipt'];
        }
        DB::purge();
        if ($receipts[0] !== $receipts[1]
            || DB::table($kind === 'invoice' ? 'transactions' : 'leases')->where('portfolio_id', $portfolio->id)->count() !== 1
            || DB::table('ai_run_steps')->where('run_id', $extraction->run_id)->where('status', 'executed')->count() !== 1) {
            throw new RuntimeException('Duplicate domain write or receipt');
        }
        echo $kind.": real database queue + two concurrent confirmations, one operation and receipt: PASS\n";
    } finally {
        foreach ($children as $child) {
            if (! $child['reaped']) {
                posix_kill($child['pid'], SIGKILL);
                pcntl_waitpid($child['pid'], $status);
            }
            fclose($child['socket']);
        }
    }
}
if (Http::recorded()->isNotEmpty()) {
    throw new RuntimeException('Unexpected HTTP call');
}
echo "No provider HTTP calls.\n";
