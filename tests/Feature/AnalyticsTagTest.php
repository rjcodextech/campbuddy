<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * The GA4 bootstrap (attendee/partials/analytics.blade.php). Rendered on its
 * own — it needs no database — so this holds regardless of the rest of the
 * suite's state.
 */
class AnalyticsTagTest extends TestCase
{
    private function render(array $data = []): string
    {
        return view('attendee.partials.analytics', $data)->render();
    }

    public function test_renders_nothing_without_a_measurement_id(): void
    {
        config(['services.google_analytics.measurement_id' => null]);

        $this->assertSame('', trim($this->render(['eventSlug' => 'wc-test'])));
    }

    public function test_renders_the_tag_when_a_measurement_id_is_set(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        $html = $this->render();

        $this->assertStringContainsString("})('G-TEST123');", $html);
        $this->assertStringContainsString('googletagmanager.com/gtag/js', $html);
        $this->assertStringContainsString("gtag('config', id", $html);
    }

    public function test_urls_are_scrubbed_before_any_hit_is_sent(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        $html = $this->render();

        // Both the page and the referrer go through the query-string scrub…
        $this->assertStringContainsString('page_location: scrub(location.href)', $html);
        $this->assertStringContainsString('page_referrer: scrub(document.referrer)', $html);
        // …which keeps only campaign params + the Explore tab, nothing else.
        $this->assertStringContainsString('utm_[a-z_]+|gclid|dclid|fbclid|msclkid|tab', $html);
    }

    public function test_advertising_features_and_browser_opt_outs_are_handled(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        $html = $this->render();

        $this->assertStringContainsString('allow_google_signals: false', $html);
        $this->assertStringContainsString('allow_ad_personalization_signals: false', $html);
        $this->assertStringContainsString('nav.globalPrivacyControl', $html);
        $this->assertStringContainsString("nav.doNotTrack === '1'", $html);
    }

    public function test_event_slug_is_a_default_param_only_when_given(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        $this->assertStringContainsString("event_slug: 'wc-test'", $this->render(['eventSlug' => 'wc-test']));
        $this->assertStringNotContainsString('event_slug', $this->render());
    }

    public function test_event_slug_cannot_break_out_of_the_script(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        $html = $this->render(['eventSlug' => '"});alert(1);//</script>']);

        // Emitted hex-escaped: the payload never appears raw, and the only
        // </script> in the output is the tag's own.
        $this->assertStringNotContainsString('alert(1);//</script>', $html);
        $this->assertSame(1, substr_count($html, '</script>'));
    }

    public function test_debug_mode_follows_app_debug(): void
    {
        config(['services.google_analytics.measurement_id' => 'G-TEST123']);

        config(['app.debug' => true]);
        $this->assertStringContainsString('debug_mode: true', $this->render());

        config(['app.debug' => false]);
        $this->assertStringNotContainsString('debug_mode', $this->render());
    }

    public function test_the_admin_layouts_do_not_include_the_tag(): void
    {
        foreach (['layouts/app', 'layouts/guest'] as $layout) {
            $this->assertStringNotContainsString(
                'partials.analytics',
                file_get_contents(resource_path("views/{$layout}.blade.php")),
                "{$layout} must stay out of attendee analytics."
            );
        }
    }
}
