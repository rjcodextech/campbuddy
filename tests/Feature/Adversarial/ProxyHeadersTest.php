<?php

namespace Tests\Feature\Adversarial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Only X-Forwarded-For and X-Forwarded-Proto are taken from the proxy. A client's own
 * X-Forwarded-Host / -Port / -Prefix (which Cloudflare passes through) must never change
 * the links the app writes, because pages and JSON are cached and shared.
 */
class ProxyHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_forged_forwarded_host_port_or_prefix_never_reaches_a_generated_link(): void
    {
        $html = $this->withHeaders([
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-Port' => '1337',
            'X-Forwarded-Prefix' => '/evil',
        ])->get('/')->assertOk()->getContent();

        $this->assertStringNotContainsString('evil.example', $html);
        $this->assertStringNotContainsString(':1337', $html);
        $this->assertStringNotContainsString('/evil/', $html);
    }

    public function test_the_forwarded_scheme_from_the_proxy_still_makes_https_links(): void
    {
        $this->withHeaders(['X-Forwarded-Proto' => 'https'])->get('/')->assertOk();

        $this->assertStringStartsWith('https://', url('/'));
    }
}
