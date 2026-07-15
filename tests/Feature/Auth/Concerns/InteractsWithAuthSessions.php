<?php

namespace Tests\Feature\Auth\Concerns;

use Illuminate\Testing\TestResponse;

trait InteractsWithAuthSessions
{
    protected function persistCookiesFrom(TestResponse $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }
    }
}
