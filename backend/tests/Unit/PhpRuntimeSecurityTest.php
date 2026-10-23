<?php

namespace Tests\Unit;

use App\Support\PhpRuntimeSecurity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhpRuntimeSecurityTest extends TestCase
{
    #[DataProvider('versions')]
    public function test_supported_official_runtimes_require_the_security_release(string $version, bool $accepted): void
    {
        $this->assertSame($accepted, PhpRuntimeSecurity::inspect($version, '0') === []);
    }

    public static function versions(): array
    {
        return [['8.3.35', false], ['8.3.36RC1', false], ['8.3.36', true], ['8.3.37', true],
            ['8.4.26', false], ['8.4.27', true], ['8.5.11', false], ['8.5.12', true],
            ['8.2.35', false], ['8.1.34', false], ['8.6.0RC4', false]];
    }

    public function test_upload_progress_must_be_disabled_even_on_a_patched_release(): void
    {
        foreach (['1', 'On', 'true', 'unexpected'] as $value) {
            $this->assertCount(1, PhpRuntimeSecurity::inspect('8.3.36', $value));
        }
        foreach (['0', 'Off', '', false] as $value) {
            $this->assertSame([], PhpRuntimeSecurity::inspect('8.3.36', $value));
        }
    }
}
