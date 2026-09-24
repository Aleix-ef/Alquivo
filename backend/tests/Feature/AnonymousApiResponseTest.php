<?php

namespace Tests\Feature;

use Tests\TestCase;

class AnonymousApiResponseTest extends TestCase
{
    public function test_anonymous_api_requests_always_return_401_instead_of_a_login_redirect(): void
    {
        foreach (['*/*', 'text/html', 'application/json'] as $accept) {
            $this->get('/api/v1/auth/me', ['Accept' => $accept])
                ->assertUnauthorized()
                ->assertHeader('Content-Type', 'application/json')
                ->assertJsonPath('message', 'Unauthenticated.');
        }
    }
}
