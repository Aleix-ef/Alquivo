<?php

namespace Tests\Feature;

use App\Domain\Assistant\Providers\FakeAIProvider;
use RuntimeException;
use Tests\Support\ProductionAiBudgetProvider;
use Tests\TestCase;

class ProductionAiBudgetTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = tempnam(sys_get_temp_dir(), 'alquivo-synthetic-budget-');
    }

    protected function tearDown(): void
    {
        unlink($this->path);
        parent::tearDown();
    }

    public function test_reservations_are_persistent_and_reject_before_a_network_call(): void
    {
        $fake = new FakeAIProvider;
        $provider = new ProductionAiBudgetProvider($fake, $this->path, 1);
        $provider->startCase('synthetic');
        try {
            $provider->generate($this->request(), 1);
            $this->fail('The phase ceiling must reject before calling the provider.');
        } catch (RuntimeException) {
            $this->assertTrue($provider->budgetExhausted());
            $this->assertSame([], $fake->requests);
            $this->assertSame(0, $provider->calls());
            $this->assertSame(0, $provider->spentNano());
        }
    }

    public function test_known_usage_uses_a_conservative_cache_write_upper_bound_and_survives_new_instances(): void
    {
        $fake = new FakeAIProvider;
        $fake->push(['usage' => ['input_tokens' => 100, 'output_tokens' => 10,
            'input_tokens_details' => ['cache_write_tokens' => 100]]]);
        $provider = new ProductionAiBudgetProvider($fake, $this->path, 1_000_000);
        $provider->startCase('synthetic');
        $provider->generate($this->request(), 1);
        $this->assertSame(17_500, $provider->spentNano());
        $again = new ProductionAiBudgetProvider($fake, $this->path, 1_000_000);
        $this->assertSame(17_500, $again->spentNano());
        $this->assertSame(1, $again->calls());
    }

    public function test_invalid_unknown_or_failed_usage_keeps_the_full_reservation(): void
    {
        $fake = new FakeAIProvider;
        $fake->push(['usage' => ['input_tokens' => 'unknown', 'output_tokens' => 1]]);
        $provider = new ProductionAiBudgetProvider($fake, $this->path, 1_000_000);
        $provider->startCase('synthetic');
        $provider->generate($this->request(), 1);
        $reserved = $provider->spentNano();
        $expected = (strlen(json_encode($this->request())) + 2048) * 125 + 10 * 500;
        $this->assertSame($expected, $reserved);
        try {
            $provider->generate($this->request(), 1); // Fake has no response: uncertain billing stays reserved.
            $this->fail('Expected the synthetic provider to fail.');
        } catch (RuntimeException) {
            $this->assertSame($reserved * 2, $provider->spentNano());
            $this->assertSame(2, $provider->calls());
        }
    }

    public function test_built_in_tools_cannot_bypass_the_token_only_phase_budget(): void
    {
        $fake = new FakeAIProvider;
        $provider = new ProductionAiBudgetProvider($fake, $this->path, 1_000_000);
        $provider->startCase('synthetic');
        $request = $this->request();
        $request['tools'] = [['type' => 'function'], ['type' => 'web_search']];
        try {
            $provider->generate($request, 1);
            $this->fail('Built-in tools can have extra charges and must not be admitted.');
        } catch (RuntimeException) {
            $this->assertSame([], $fake->requests);
            $this->assertSame(0, $provider->spentNano());
        }
    }

    private function request(): array
    {
        return ['model' => 'gpt-6-luna', 'store' => false, 'max_output_tokens' => 10, 'input' => 'Synthetic only'];
    }
}
