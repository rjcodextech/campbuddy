<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * www.campbuddy.club sends people to campbuddy.club (GA showed visits on
 * both, and the browser keeps an attendee's data per address).
 */
class RedirectWwwTest extends TestCase
{
    public function test_www_redirects_to_the_bare_domain_with_path_and_query(): void
    {
        $this->get('https://www.campbuddy.club/event/wordcamp-rajasthan-2026/my-day?utm_source=x')
            ->assertStatus(301)
            ->assertRedirect('https://campbuddy.club/event/wordcamp-rajasthan-2026/my-day?utm_source=x');
    }

    public function test_behind_cloudflare_the_redirect_stays_https(): void
    {
        $this->get('http://www.campbuddy.club/guide', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->assertRedirect('https://campbuddy.club/guide');
    }

    public function test_a_post_keeps_its_method(): void
    {
        $this->post('https://www.campbuddy.club/api/anything')->assertStatus(308);
    }

    public function test_the_bare_domain_is_untouched(): void
    {
        $this->get('https://campbuddy.club/robots.txt')->assertOk();
    }
}
