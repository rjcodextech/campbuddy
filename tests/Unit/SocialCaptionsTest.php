<?php

namespace Tests\Unit;

use App\Support\SocialCaptions;
use PHPUnit\Framework\TestCase;

/**
 * The Social media studio's captions (SocialCaptions): one each for Instagram,
 * LinkedIn and X, inside each network's limits; tests/js/social-captions.test.mjs
 * checks the browser's builder gives the same from the same fixture.
 */
class SocialCaptionsTest extends TestCase
{
    /** @return list<array{name: string, parts: array, hashtags: string, captions: array}> */
    private function cases(): array
    {
        return json_decode((string) file_get_contents(__DIR__.'/../Fixtures/social-captions.json'), true);
    }

    public static function assertInLimits(array $captions, string $label): void
    {
        self::assertLessThanOrEqual(280, SocialCaptions::xLength($captions['x']), "{$label}: X");
        self::assertLessThanOrEqual(2200, mb_strlen($captions['instagram']), "{$label}: Instagram length");
        self::assertLessThanOrEqual(3000, mb_strlen($captions['linkedin']), "{$label}: LinkedIn length");
        self::assertLessThanOrEqual(5, SocialCaptions::tagCount($captions['instagram']), "{$label}: Instagram hashtags");
        self::assertLessThanOrEqual(5, SocialCaptions::tagCount($captions['linkedin']), "{$label}: LinkedIn hashtags");
        self::assertDoesNotMatchRegularExpression('~https?://~', $captions['instagram'], "{$label}: no link on Instagram");
    }

    public function test_the_server_gives_the_fixtures_captions(): void
    {
        $this->assertGreaterThanOrEqual(7, count($this->cases()));
        foreach ($this->cases() as $case) {
            $this->assertSame($case['captions'], SocialCaptions::make($case['parts'], $case['hashtags']), $case['name']);
            self::assertInLimits($case['captions'], $case['name']);
        }
    }

    public function test_x_counts_like_x(): void
    {
        $this->assertSame(23, SocialCaptions::xLength('https://campbuddy.club/event/a-long-slug?utm_source=campbuddy&utm_content=x'));
        $this->assertSame(23, SocialCaptions::xLength('wpsimplified.in'));
        $this->assertSame(2, SocialCaptions::xLength('🎉'));
        $this->assertSame(7, SocialCaptions::xLength('3–4 Oct'));
    }

    public function test_names_tags_and_the_text_file(): void
    {
        $this->assertSame('A, B and C', SocialCaptions::names(['A', ' B ', '', 'C']));
        $this->assertSame('Rahul Sharma', SocialCaptions::names(['Rahul Sharma']));
        $this->assertSame(['#WCRajasthan', '#WordCamp', '#WordPress', '#CampBuddy', '#WPSimplified'], SocialCaptions::tags('#WordCamp #WordPress #WCRajasthan #WCRajasthan2026'));
        $this->assertSame(['#WordCamp', '#WordPress', '#CampBuddy', '#WPSimplified'], SocialCaptions::tags(''));
        $this->assertSame(2, SocialCaptions::tagCount('#a and #b, not a#c or &#039;'));
    }
}
