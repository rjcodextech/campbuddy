<?php

namespace Tests\Feature\Adversarial;

use App\Support\SafeHtml;
use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SafeHtml cleans what an admin writes in the legal-page editor before it is
 * shown to every attendee. Thrown at it: the classic filter-evasion list —
 * script and event handlers, script URLs hidden behind entities, control
 * characters and odd case, frames, SVG/MathML, style tricks, and the
 * mutation-XSS wrappers (noscript, noembed, xmp, title, textarea, comments)
 * where a sanitiser's parser and the browser's disagree.
 */
class PanelSafeHtmlTest extends TestCase
{
    private const ALLOWED_TAGS = ['p', 'br', 'hr', 'div', 'span', 'section', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 's',
        'strike', 'del', 'ins', 'mark', 'small', 'sub', 'sup', 'code', 'pre', 'blockquote', 'ul', 'ol', 'li', 'a', 'table', 'thead', 'tbody', 'tfoot',
        'tr', 'caption', 'th', 'td'];

    /** @return array<string, array{0: string}> */
    public static function payloads(): array
    {
        $list = [
            '<script>alert(1)</script>',
            '<img src=x onerror=alert(1)>',
            '<a href="javascript:alert(1)">x</a>',
            '<a href="JaVaScRiPt:alert(1)">x</a>',
            '<a href=javascript:alert(1)>x</a>',
            "<a href='javascript:alert(1)'>x</a>",
            '<a href="java&#x09;script:alert(1)">x</a>',
            '<a href="jav&NewLine;ascript:alert(1)">x</a>',
            '<a href="javascript&colon;alert(1)">x</a>',
            '<a href="&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;alert(1)">x</a>',
            '<a href="&#0000106avascript:alert(1)">x</a>',
            "<a href=\"\x01javascript:alert(1)\">x</a>",
            "<a href=\"java\x00script:alert(1)\">x</a>",
            '<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">x</a>',
            '<a href="vbscript:msgbox(1)">x</a>',
            '<a href="#" onclick="alert(1)">x</a>',
            '<iframe src="javascript:alert(1)"></iframe>',
            '<iframe srcdoc="<script>alert(1)</script>"></iframe>',
            '<svg onload=alert(1)>',
            '<svg><script>alert(1)</script></svg>',
            '<svg><a xlink:href="javascript:alert(1)"><text>x</text></a></svg>',
            '<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>',
            '<style>@import "//evil.example/x.css";</style>',
            '<p style="background:url(javascript:alert(1))">x</p>',
            '<noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>',
            '<noembed><p title="</noembed><img src=x onerror=alert(1)>"></noembed>',
            '<xmp><p title="</xmp><img src=x onerror=alert(1)>"></xmp>',
            '<title><p title="</title><img src=x onerror=alert(1)>"></title>',
            '<textarea><p title="</textarea><img src=x onerror=alert(1)>"></textarea>',
            '<p><!--><img src=x onerror=alert(1)>--></p>',
            '<!--<img src="--><img src=x onerror=alert(1)//">',
            '</div><script>alert(1)</script><div>',
            '<div id="safe-html-root"><script>alert(1)</script></div>',
            '<form action="javascript:alert(1)"><button formaction="javascript:alert(1)">x</button></form>',
            '<details open ontoggle=alert(1)>',
            '<object data="javascript:alert(1)"></object>',
            '<embed src="javascript:alert(1)">',
            '<base href="javascript:/">',
            '<meta http-equiv="refresh" content="0;url=javascript:alert(1)">',
            '<link rel="stylesheet" href="//evil.example/x.css">',
            '<table background="javascript:alert(1)"><tr><td background="javascript:alert(1)">x</td></tr></table>',
            '<scr<script>ipt>alert(1)</scr</script>ipt>',
            '<![CDATA[<script>alert(1)</script>]]>',
            '<p>ok</p><?php echo 1; ?>',
            '<a href="https://ok.example" title="&quot;\' onmouseover=alert(1) x=\'">x</a>',
            '<template><img src=x onerror=alert(1)></template>',
            '<video><source onerror=alert(1)></video>',
            '<marquee onstart=alert(1)>x</marquee>',
            '<body onload=alert(1)>',
            '<input autofocus onfocus=alert(1)>',
            '<select onfocus=alert(1) autofocus>',
            '<keygen autofocus onfocus=alert(1)>',
        ];

        return array_combine(array_map(fn ($p) => mb_substr($p, 0, 60), $list), array_map(fn ($p) => [$p], $list));
    }

    #[DataProvider('payloads')]
    public function test_nothing_that_can_run_script_survives_cleaning(string $payload): void
    {
        $clean = SafeHtml::clean($payload);

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>'.$clean.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_use_internal_errors($previous);

        foreach ((new DOMXPath($doc))->query('//*') as $el) {
            /** @var DOMElement $el */
            $this->assertContains(strtolower($el->tagName), [...self::ALLOWED_TAGS, 'div'], "<{$el->tagName}> survived: {$clean}");
            foreach ($el->attributes as $attribute) {
                $name = strtolower($attribute->name);
                $this->assertDoesNotMatchRegularExpression('/^(on|style$|srcdoc$|formaction$|background$|xlink:)/', $name, "{$name}= survived: {$clean}");
                if (in_array($name, ['href', 'src', 'action'], true)) {
                    $compact = preg_replace('/[\x00-\x20\x7F]+/', '', html_entity_decode($attribute->value, ENT_QUOTES | ENT_HTML5));
                    $this->assertDoesNotMatchRegularExpression('/^(javascript|vbscript|data):/i', $compact, "a script URL survived: {$clean}");
                }
            }
        }

        // Cleaning twice changes nothing: the output is already in the shape the parser keeps.
        $this->assertSame($clean, SafeHtml::clean($clean), 'cleaning is not stable');
    }

    public function test_what_the_editor_makes_is_kept(): void
    {
        $html = '<h2 id="who">Who we are</h2><p class="lead"><strong>Bold</strong>, <em>em</em> and <a href="https://campbuddy.club/privacy" target="_blank">a link</a>.</p>'
            .'<ul><li>One</li></ul><ol start="2" type="a"><li>Two</li></ol><blockquote>Quote</blockquote><pre><code>code</code></pre>'
            .'<div class="callout"><p>Note</p></div><table><thead><tr><th scope="col" data-label="Data">Data</th></tr></thead><tbody><tr><td data-label="Data">x</td></tr></tbody></table>'
            .'<p><a href="mailto:privacy@example.com">mail</a> <a href="tel:+911234567890">call</a> <a href="#who">top</a></p>';

        $clean = SafeHtml::clean($html);

        $this->assertStringContainsString('<h2 id="who">Who we are</h2>', $clean);
        $this->assertStringContainsString('<a href="https://campbuddy.club/privacy" target="_blank" rel="noopener noreferrer">a link</a>', $clean);
        $this->assertStringContainsString('<ol start="2" type="a">', $clean);
        $this->assertStringContainsString('<div class="callout">', $clean);
        $this->assertStringContainsString('<th scope="col" data-label="Data">', $clean);
        $this->assertStringContainsString('href="mailto:privacy@example.com"', $clean);
        $this->assertStringContainsString('href="tel:+911234567890"', $clean);
        $this->assertStringContainsString('href="#who"', $clean);
    }

    public function test_a_link_opening_a_new_tab_can_not_reach_back_to_the_page(): void
    {
        $this->assertSame(
            '<a href="https://ok.example" target="_blank" rel="noopener noreferrer">x</a>',
            SafeHtml::clean('<a href="https://ok.example" target="_self" rel="opener">x</a>')
        );
    }

    public function test_text_and_unicode_come_through_and_empty_input_is_empty(): void
    {
        $this->assertSame('', SafeHtml::clean(null));
        $this->assertSame('', SafeHtml::clean("  \n "));
        $this->assertSame('<p>Café — नमस्ते 😀 &lt;b&gt;</p>', SafeHtml::clean('<p>Café — नमस्ते 😀 &lt;b&gt;</p>'));
    }

    public function test_very_deep_or_very_long_input_finishes_quickly(): void
    {
        $started = microtime(true);
        $deep = SafeHtml::clean(str_repeat('<div>', 5000).'x'.str_repeat('</div>', 5000));
        $long = SafeHtml::clean(str_repeat('<p onclick="alert(1)">'.str_repeat('a', 100).'</p>', 5000));

        $this->assertLessThan(10, microtime(true) - $started);
        $this->assertStringNotContainsString('onclick', $long);
        // Past the parser's nesting limit the rest is dropped (lost, never let through).
        $this->assertMatchesRegularExpression('#^(<div>|</div>|x)*$#', $deep);
    }
}
