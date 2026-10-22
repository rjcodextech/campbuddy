<?php

namespace Tests\Unit;

use App\Support\SafeHtml;
use PHPUnit\Framework\TestCase;

class SafeHtmlTest extends TestCase
{
    public function test_keeps_what_the_legal_editor_makes(): void
    {
        $html = '<h2>Who we are</h2><p>We keep <strong>little</strong> and <em>briefly</em>. <a href="https://campbuddy.club/privacy" target="_blank">Read more</a></p>'
            .'<div class="legal__box"><ul><li>One</li></ul></div><table><tr><td data-label="What">Name</td></tr></table><blockquote>Q</blockquote><pre><code>x</code></pre><hr>';
        $clean = SafeHtml::clean($html);

        foreach (['<h2>Who we are</h2>', '<strong>little</strong>', '<em>briefly</em>', 'href="https://campbuddy.club/privacy"', 'rel="noopener noreferrer"', 'class="legal__box"', '<li>One</li>', 'data-label="What"', '<blockquote>Q</blockquote>', '<code>x</code>', '<hr>'] as $kept) {
            $this->assertStringContainsString($kept, $clean);
        }
    }

    public function test_drops_scripts_handlers_and_script_links(): void
    {
        $html = '<p onclick="steal()">Hi<script>alert(1)</script></p><img src=x onerror=alert(1)><iframe src="https://evil.test"></iframe>'
            .'<a href="javascript:alert(1)">a</a><a href=" java&#09;script:alert(1)">b</a><a href="data:text/html,<script>x</script>">c</a>'
            .'<style>body{display:none}</style><svg onload=alert(1)><circle/></svg><p style="background:url(x)">s</p><!-- note --><form action="/x"><input name="q"></form>';
        $clean = SafeHtml::clean($html);

        foreach (['<script', 'onclick', 'onerror', '<img', '<iframe', 'javascript:', 'data:text', '<style', '<svg', 'onload', 'style=', '<!--', '<form', '<input'] as $gone) {
            $this->assertStringNotContainsStringIgnoringCase($gone, $clean);
        }
        $this->assertStringContainsString('<p>Hi</p>', $clean);
        $this->assertStringContainsString('<a>a</a>', $clean);
    }

    public function test_keeps_relative_mail_and_phone_links_and_unicode(): void
    {
        $clean = SafeHtml::clean('<p><a href="/terms">Terms</a> <a href="#rights">Rights</a> <a href="mailto:hi@example.test">Mail</a> <a href="tel:+911234">Call</a> नमस्ते</p>');

        $this->assertStringContainsString('href="/terms"', $clean);
        $this->assertStringContainsString('href="#rights"', $clean);
        $this->assertStringContainsString('href="mailto:hi@example.test"', $clean);
        $this->assertStringContainsString('href="tel:+911234"', $clean);
        $this->assertStringContainsString('नमस्ते', $clean);
    }

    public function test_empty_input_is_empty(): void
    {
        $this->assertSame('', SafeHtml::clean(null));
        $this->assertSame('', SafeHtml::clean('   '));
    }
}
