<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;

class HttpHeaderParsingTest extends TestCase
{
    public function test_security_patch_preserves_whitespace_separators(): void
    {
        $this->assertSame(['foo', 'bar'], HeaderUtils::split('foo bar', ' '));
    }

    public function test_cache_control_with_long_whitespace_keeps_its_directives(): void
    {
        // Bounded synthetic input; never probe a public server with hostile headers.
        $request = Request::create('/', server: ['HTTP_CACHE_CONTROL' => 'no-cache'.str_repeat(' ', 24).', max-age=0']);
        $this->assertTrue($request->headers->hasCacheControlDirective('no-cache'));
        $this->assertSame('0', $request->headers->getCacheControlDirective('max-age'));
    }
}
