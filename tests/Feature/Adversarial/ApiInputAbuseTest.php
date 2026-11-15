<?php

namespace Tests\Feature\Adversarial;

use App\Models\DiscoveryProfile;
use App\Models\Event;
use App\Models\SharedCampCard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Every attendee-facing write takes input from anyone on the internet. Whatever
 * arrives — the wrong type, nothing, a megabyte, JSON nested a thousand deep,
 * markup, SQL, invisible Unicode — the answer is a clean 4xx in JSON (never a
 * 500, never a stack trace), and what is stored is exactly what the rules allow.
 */
class ApiInputAbuseTest extends TestCase
{
    use ApiFixtures;
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-10T09:00:00Z'));
        config([
            'app.debug' => false,
            // Hundreds of requests from one test address: only the per-phone limits matter here.
            'campbuddy.rate_limits.address_writes' => 100000,
            'campbuddy.rate_limits.address_reads' => 100000,
        ]);

        $this->event = $this->makeEvent();
    }

    /** The values a hostile client sends where a scalar belongs. */
    private function hostileValues(): array
    {
        return [
            'list' => ['a', 'b'],
            'nested object' => ['deep' => ['deeper' => ['deepest' => 'x']]],
            'boolean' => true,
            'number' => 12345,
            'one megabyte' => str_repeat('A', 1024 * 1024),
        ];
    }

    private function assertRefusedCleanly(TestResponse $response, string $context): void
    {
        $status = $response->getStatusCode();
        $this->assertTrue($status >= 400 && $status < 500, "{$context}: expected a 4xx, got {$status}: ".mb_substr((string) $response->getContent(), 0, 300));
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'), "{$context}: the error must be JSON");
        $this->assertNoDebugLeak($response);
    }

    /**
     * @param  array<string, mixed>  $valid  a body the endpoint would accept
     * @param  list<string>  $fields  dotted paths to poison one at a time
     */
    private function poisonEachField(string $method, string $url, array $valid, array $fields, array $headers = []): void
    {
        foreach ($fields as $field) {
            foreach ($this->hostileValues() as $label => $value) {
                $body = $valid;
                data_set($body, $field, $value);

                $response = $this->json($method, $url, $body, $headers + $this->phone());

                // A string field may legitimately accept a long number or "1" for true;
                // what matters is that nothing crashes and nothing leaks.
                if ($response->getStatusCode() < 400) {
                    $this->assertLessThan(300, $response->getStatusCode());

                    continue;
                }

                $this->assertRefusedCleanly($response, "{$method} {$url} with {$field} = {$label}");
            }
        }
    }

    public function test_discovery_join_and_edit_answer_every_wrong_type_with_a_clean_4xx(): void
    {
        $entry = $this->rosterEntry($this->event, 'Asha Rao');
        $valid = ['tags' => ['developer'], 'profession' => 'Dev', 'who_to_meet' => 'Designers', 'display_name' => 'Asha', 'wporg_username' => 'asha'];
        $fields = ['tags', 'tags.0', 'profession', 'who_to_meet', 'display_name', 'wporg_username', 'attendee_roster_id', 'consent'];

        $this->poisonEachField('POST', route('api.discovery.store', $this->event), $valid, $fields);

        $me = $this->joinDiscovery($this->event);
        $this->poisonEachField('PATCH', route('api.discovery.update', [$this->event, $me['discovery_id']]), $valid + ['attendee_roster_id' => $entry->id], $fields, $this->bearer($me['owner_token']));
    }

    public function test_waves_messages_and_reports_answer_every_wrong_type_with_a_clean_4xx(): void
    {
        $me = $this->joinDiscovery($this->event, ['display_name' => 'Asha']);
        $them = $this->joinDiscovery($this->event, ['display_name' => 'Ben']);
        $auth = $this->bearer($me['owner_token']);

        $this->poisonEachField('POST', route('api.discovery.waves.store', [$this->event, $me['discovery_id']]), ['to' => $them['discovery_id'], 'name' => 'Asha'], ['to', 'name', 'message'], $auth);
        $this->poisonEachField('POST', route('api.discovery.messages.store', [$this->event, $me['discovery_id']]), ['to' => $them['discovery_id'], 'body' => 'Hi'], ['to', 'body'], $auth);
        $this->poisonEachField('POST', route('api.discovery.report', [$this->event, $them['discovery_id']]), ['device_id' => 'phone-1', 'reason' => 'spam'], ['device_id', 'reason', 'note']);
    }

    public function test_device_transfer_calls_answer_every_wrong_type_with_a_clean_4xx(): void
    {
        $entry = $this->rosterEntry($this->event, 'Asha Rao');
        $me = $this->joinDiscovery($this->event, ['attendee_roster_id' => $entry->id]);

        $this->poisonEachField('POST', route('api.device-transfers.store', $this->event), ['attendee_roster_id' => $entry->id, 'public_key' => $this->base64url(65)], ['attendee_roster_id', 'public_key']);

        $transfer = $this->postJson(route('api.device-transfers.store', $this->event), ['attendee_roster_id' => $entry->id, 'public_key' => $this->base64url(65)], $this->phone())->assertCreated();
        $this->poisonEachField(
            'POST',
            route('api.device-transfers.approve', [$this->event, $me['discovery_id'], $transfer->json('transfer_id')]),
            ['sender_key' => $this->base64url(65), 'iv' => $this->base64url(12), 'payload' => $this->base64url(300)],
            ['sender_key', 'iv', 'payload'],
            $this->bearer($me['owner_token']),
        );
    }

    public function test_camp_card_sharing_answers_every_wrong_type_with_a_clean_4xx(): void
    {
        $entry = $this->rosterEntry($this->event, 'Asha Rao');
        $valid = ['attendee_roster_id' => $entry->id, 'card' => ['role' => 'Dev', 'interests' => ['PHP'], 'qr' => ['type' => 'website', 'url' => 'https://example.com']]];
        $fields = ['attendee_roster_id', 'discovery_id', 'discovery_token', 'card', 'card.role', 'card.company', 'card.city', 'card.askMeAbout', 'card.interests', 'card.interests.0', 'card.qr', 'card.qr.type', 'card.qr.url'];

        $this->poisonEachField('POST', route('api.camp-card-share.store', $this->event), $valid, $fields);

        // Some poisoned bodies were still valid (a number in the interests is dropped) and took the name.
        SharedCampCard::query()->delete();
        $share = $this->postJson(route('api.camp-card-share.store', $this->event), $valid, $this->phone())->assertCreated();
        $this->poisonEachField('PUT', route('api.camp-card-share.update', [$this->event, $share->json('share_id')]), $valid, $fields, $this->bearer($share->json('owner_token')));
    }

    public function test_push_bookmark_and_presence_writes_answer_every_wrong_type_with_a_clean_4xx(): void
    {
        $this->poisonEachField('POST', route('api.push.subscribe', $this->event), [
            // An address literal, so validation needs no DNS lookup.
            'device_id' => 'phone-1', 'endpoint' => 'https://93.184.216.34/push/abc', 'keys' => ['p256dh' => 'key', 'auth' => 'auth'],
        ], ['device_id', 'endpoint', 'keys', 'keys.p256dh', 'keys.auth']);

        $this->poisonEachField('POST', route('api.push.native-subscribe', $this->event), [
            'device_id' => 'phone-1', 'platform' => 'android', 'token' => str_repeat('a', 40), 'app_version' => '1.2.3',
        ], ['device_id', 'platform', 'token', 'app_version']);
        $this->poisonEachField('DELETE', route('api.push.native-unsubscribe', $this->event), ['device_id' => 'phone-1'], ['device_id']);

        $this->poisonEachField('POST', route('api.events.seen', $this->event), ['device_id' => 'phone-0001', 'platform' => 'web'], ['device_id', 'platform']);

        $this->poisonEachField('POST', route('api.bookmarks.store', $this->event), ['device_id' => 'phone-1', 'session_id' => 7, 'reminder_enabled' => true], ['device_id', 'session_id', 'reminder_enabled']);
        $this->poisonEachField('DELETE', route('api.bookmarks.destroy', $this->event), ['device_id' => 'phone-1', 'session_id' => 7], ['device_id', 'session_id']);
    }

    public function test_deal_leads_suggestions_and_ratings_answer_every_wrong_type_with_a_clean_4xx(): void
    {
        $offer = $this->leadOffer($this->event);

        $this->poisonEachField('POST', route('api.offers.leads.store', [$this->event, $offer]), ['name' => 'Ada', 'email' => 'ada@example.com'], ['name', 'email', 'mobile', 'company', 'choices']);
        $this->poisonEachField('POST', route('api.free-steal-suggestions.store', $this->event), ['name' => 'Plugin', 'url' => 'https://example.com/plugin'], ['name', 'url', 'maker', 'why', 'email', 'website']);
        $this->poisonEachField('POST', route('api.feedback.store', $this->event), ['device_id' => 'phone-1', 'rating' => 5], ['device_id', 'rating', 'comment', 'website']);
    }

    public function test_the_organizer_sign_in_answers_every_wrong_type_with_a_clean_4xx(): void
    {
        $i = 0;
        foreach (['email', 'password', 'role', 'device_name'] as $field) {
            foreach ($this->hostileValues() as $label => $value) {
                $body = ['email' => 'nobody@example.com', 'password' => 'not-a-real-password', 'role' => 'admin', 'device_name' => 'Pixel'];
                $body[$field] = $value;

                // A different address each time, so the sign-in throttle doesn't decide the answer.
                $response = $this->withServerVariables(['REMOTE_ADDR' => '10.9.0.'.(++$i)])->postJson(route('api.app.auth.login'), $body);

                $this->assertRefusedCleanly($response, "login with {$field} = {$label}");
            }
        }
    }

    public function test_every_organizer_write_answers_a_wrong_type_with_a_clean_4xx(): void
    {
        // The throttles are tested elsewhere; here every one of several hundred requests must reach validation.
        $this->withoutMiddleware(ThrottleRequests::class);
        Http::fake();
        Queue::fake();

        $admin = User::factory()->create();
        $auth = $this->bearer($this->organizerToken('admin', $admin->id));
        $entry = $this->rosterEntry($this->event, 'Asha Rao');
        $base = "/api/v1/app/organizer/events/{$this->event->id}";

        $endpoints = [
            ['POST', "{$base}/info", ['wifi', 'venue', 'emergency_contact', 'registration_info', 'contributor_day_location']],
            ['POST', "{$base}/details", ['slug', 'display_name', 'short_name', 'source_site_url', 'starts_on', 'ends_on', 'timezone', 'is_visible', 'status']],
            ['POST', "{$base}/information", ['venue', 'important_links', 'wifi', 'social_event_info', 'registration_info', 'contributor_day_location', 'code_of_conduct_url', 'emergency_contact', 'nearby_venue_info']],
            ['POST', "{$base}/quests", ['title', 'description', 'sort_order', 'id', 'source']],
            ['POST', "{$base}/tables", ['team', 'title', 'track', 'floor', 'table_no', 'leads', 'note', 'sort_order']],
            ['POST', "{$base}/deals", ['brand', 'title', 'url', 'website', 'description', 'terms', 'coupon_code', 'cta_label', 'highlight', 'icon', 'is_active', 'capture_leads', 'opens_in_app', 'sort_order']],
            ['POST', "{$base}/delay", ['minutes', 'track', 'from', 'note']],
            ['POST', "{$base}/memories", ['photo_album_url', 'wordpress_tv_url']],
            ['POST', "{$base}/broadcasts", ['kind', 'title', 'body', 'where', 'session_id', 'tab']],
            ['POST', "{$base}/attendees/{$entry->id}/roles", ['roles', 'roles.0']],
            ['POST', "{$base}/social/colors", ['primary', 'secondary', 'ink', 'paper']],
            ['POST', "{$base}/social/art/kind", ['piece', 'kind']],
            ['POST', "{$base}/social/webhook", ['webhook', 'remove']],
            ['POST', "{$base}/social/publish", ['image', 'title', 'caption', 'kind', 'captions', 'captions.x']],
            ['DELETE', $base, ['confirm']],
            ['POST', '/api/v1/app/organizer/admin/managers', ['name', 'email', 'phone', 'password', 'is_active', 'events', 'events.0']],
            ['POST', '/api/v1/app/organizer/admin/events', ['slug', 'display_name', 'short_name', 'source_site_url', 'starts_on', 'ends_on', 'timezone', 'status', 'is_visible']],
        ];

        foreach ($endpoints as [$method, $url, $fields]) {
            foreach ($fields as $field) {
                foreach ($this->hostileValues() as $label => $value) {
                    $body = [];
                    data_set($body, $field, $value);
                    $response = $this->json($method, $url, $body, $auth);

                    $this->assertLessThan(500, $response->getStatusCode(), "{$method} {$url} with {$field} = {$label} crashed: ".mb_substr((string) $response->getContent(), 0, 200));
                    $this->assertNoDebugLeak($response);
                }
            }
        }

        $this->assertNotNull($this->event->fresh(), 'nothing above deleted the event');
    }

    public function test_query_strings_of_the_wrong_type_never_crash_a_read(): void
    {
        $admin = User::factory()->create();
        $auth = $this->bearer($this->organizerToken('admin', $admin->id));
        $slug = $this->event->slug;

        $public = [
            '/api/v1/app/events?country[]=IN',
            '/api/v1/app/events?include[]=live',
            "/api/v1/events/{$slug}/roster?page[]=2",
            "/api/v1/events/{$slug}/roster?page[a][b]=2",
            "/api/v1/app/events/{$slug}/bundle?since[]=x",
            '/api/v1/app/update?package[]=club.campbuddy.app&code[]=15',
        ];
        foreach ($public as $url) {
            $response = $this->getJson($url, $this->phone());
            $this->assertLessThan(500, $response->getStatusCode(), "{$url} crashed: ".mb_substr((string) $response->getContent(), 0, 300));
            $this->assertNoDebugLeak($response);
        }

        $organizer = [
            '/api/v1/app/organizer/events?q[]=x&status[]=active&when[]=past&order[]=soon',
            "/api/v1/app/organizer/events/{$this->event->id}/attendees?q[]=x&role[]=speaker&status[]=all&page[]=2",
            "/api/v1/app/organizer/events/{$this->event->id}/leads?offer_id[]=1",
            '/api/v1/app/organizer/admin/managers?q[]=x',
            '/api/v1/app/organizer/admin/activity?event[]=1&manager[]=1',
            '/api/v1/app/organizer/admin/feedback?event[]=1',
        ];
        foreach ($organizer as $url) {
            $response = $this->getJson($url, $auth);
            $this->assertLessThan(500, $response->getStatusCode(), "{$url} crashed: ".mb_substr((string) $response->getContent(), 0, 300));
            $this->assertNoDebugLeak($response);
        }
    }

    public function test_an_oddly_written_page_number_cannot_poison_the_shared_roster_page(): void
    {
        foreach (range(1, 205) as $i) {
            $this->rosterEntry($this->event, sprintf('Person %03d', $i));
        }

        // "02" and "2x" read as page 2 for the cache, but the paginator used to read them as page 1:
        // everyone asking for page 2 for the next minute got page 1 again (and the CDN kept it).
        foreach (['02', '2x'] as $odd) {
            Cache::flush();
            $this->getJson("/api/v1/events/{$this->event->slug}/roster?page={$odd}")->assertOk();

            $page2 = $this->getJson("/api/v1/events/{$this->event->slug}/roster?page=2")->assertOk();
            $this->assertSame(2, $page2->json('current_page'), "after ?page={$odd}");
            $this->assertSame('Person 201', $page2->json('data.0.name'), "after ?page={$odd}");
        }
    }

    public function test_json_nested_a_thousand_deep_is_refused_cleanly(): void
    {
        $deep = str_repeat('{"a":', 1000).'1'.str_repeat('}', 1000);

        $response = $this->call('POST', route('api.discovery.store', $this->event), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_CAMPBUDDY_DEVICE' => '0f0e0d0c-0b0a-4908-8706-050403020100',
        ], $deep);

        $this->assertRefusedCleanly($response, 'deeply nested JSON');
        $this->assertSame(0, DiscoveryProfile::count());
    }

    public function test_malformed_json_is_refused_cleanly(): void
    {
        $response = $this->call('POST', route('api.discovery.store', $this->event), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
        ], '{"tags": ["developer"');

        $this->assertRefusedCleanly($response, 'truncated JSON');
    }

    public function test_out_of_range_and_negative_ids_are_refused_cleanly(): void
    {
        foreach (['99999999999999999999999', -1, 0, '1 OR 1=1', '1e3', 1.5, '0x1A'] as $id) {
            $this->assertRefusedCleanly(
                $this->postJson(route('api.discovery.store', $this->event), ['tags' => ['developer'], 'attendee_roster_id' => $id], $this->phone()),
                "discovery attendee_roster_id {$id}",
            );
            $this->assertRefusedCleanly(
                $this->postJson(route('api.camp-card-share.store', $this->event), ['attendee_roster_id' => $id, 'card' => ['role' => 'Dev']], $this->phone()),
                "camp card attendee_roster_id {$id}",
            );
        }

        foreach (['-1', '99999999999999999999999', 'abc', '1;DROP'] as $offer) {
            $this->assertRefusedCleanly($this->postJson("/api/v1/events/{$this->event->slug}/offers/{$offer}/leads", ['name' => 'Ada', 'email' => 'ada@example.com'], $this->phone()), "offer id {$offer}");
        }

        $this->assertSame(0, DiscoveryProfile::count());
        $this->assertSame(0, SharedCampCard::count());
    }

    public function test_an_organizer_route_with_an_id_too_big_for_an_integer_is_a_404_not_a_crash(): void
    {
        $admin = User::factory()->create();
        $auth = $this->bearer($this->organizerToken('admin', $admin->id));
        $huge = '99999999999999999999999';

        foreach ([
            "/api/v1/app/organizer/events/{$huge}/overview",
            "/api/v1/app/organizer/events/{$huge}/details",
            "/api/v1/app/organizer/events/{$huge}/social",
        ] as $url) {
            $response = $this->getJson($url, $auth);
            $response->assertNotFound();
            $this->assertNoDebugLeak($response);
        }

        $this->assertRefusedCleanly(
            $this->postJson("/api/v1/app/organizer/events/{$this->event->id}/attendees/{$huge}/roles", ['roles' => []], $auth),
            'attendee entry id too big',
        );
        $this->assertRefusedCleanly(
            $this->patchJson("/api/v1/app/organizer/events/{$this->event->id}/quests/{$huge}", ['title' => 'x'], $auth),
            'quest id too big',
        );
    }

    public function test_markup_and_sql_are_stored_as_plain_text_and_harm_nothing(): void
    {
        $payload = "'; DROP TABLE discovery_profiles; -- <script>alert(1)</script>";

        $created = $this->postJson(route('api.discovery.store', $this->event), [
            'tags' => ['developer'], 'profession' => mb_substr($payload, 0, 100), 'who_to_meet' => $payload, 'display_name' => '<img src=x onerror=alert(1)>',
        ], $this->phone())->assertCreated();

        $this->assertTrue(Schema::hasTable('discovery_profiles'));
        $card = collect($this->getJson(route('api.discovery.index', $this->event))->json('data'))->firstWhere('discovery_id', $created->json('discovery_id'));
        $this->assertSame($payload, $card['fields']['who_to_meet'], 'stored verbatim — escaping is the renderer\'s job');
        $this->assertSame('<img src=x onerror=alert(1)>', $card['name']);
    }

    public function test_text_shown_to_other_attendees_loses_bidi_overrides_and_control_characters_but_keeps_emoji(): void
    {
        $rtl = "\u{202E}";
        $created = $this->postJson(route('api.discovery.store', $this->event), [
            'tags' => ['developer'],
            'profession' => "Dev{$rtl}gnp.exe\u{0007}",
            'who_to_meet' => "Designers\u{2066}\u{0000} and \u{200B}PMs 👩‍💻",
            'display_name' => "Asha{$rtl}\u{200B} Rao",
        ], $this->phone())->assertCreated();

        $card = collect($this->getJson(route('api.discovery.index', $this->event))->json('data'))->firstWhere('discovery_id', $created->json('discovery_id'));

        $this->assertSame('Asha Rao', $card['name']);
        foreach (['profession', 'who_to_meet'] as $field) {
            $value = $card['fields'][$field];
            $this->assertDoesNotMatchRegularExpression('/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200B}\x00-\x1F\x7F]/u', $value, "{$field} still carries invisible or direction-changing characters");
        }
        $this->assertSame('Devgnp.exe', $card['fields']['profession']);
        $this->assertStringContainsString('👩‍💻', $card['fields']['who_to_meet'], 'an emoji joined with a zero-width joiner stays whole');
    }

    public function test_devanagari_and_emoji_survive_every_shared_field_intact(): void
    {
        $entry = $this->rosterEntry($this->event, 'अमित शर्मा');
        $hindi = 'वर्डप्रेस डेवलपर';

        $created = $this->postJson(route('api.discovery.store', $this->event), [
            'tags' => ['developer'], 'profession' => $hindi, 'who_to_meet' => 'डिज़ाइनर 🎨', 'display_name' => 'अनु',
        ], $this->phone())->assertCreated();
        $this->assertSame($hindi, $created->json('fields.profession'));
        $this->assertSame('अनु', $created->json('name'));

        $share = $this->postJson(route('api.camp-card-share.store', $this->event), [
            'attendee_roster_id' => $entry->id, 'card' => ['role' => 'डेवलपर', 'city' => 'दिल्ली', 'interests' => ['हिंदी 🙏']],
        ], $this->phone())->assertCreated();
        $this->assertSame('दिल्ली', $share->json('card.city'));
        $this->assertSame(['हिंदी 🙏'], $share->json('card.interests'));
    }

    public function test_forged_ownership_and_lifecycle_fields_are_ignored_on_create(): void
    {
        $other = $this->makeEvent('wc-other');
        $forgedToken = 'forged-owner-token-placeholder';

        $created = $this->postJson(route('api.discovery.store', $this->event), [
            'tags' => ['developer'],
            'event_id' => $other->id,
            'discovery_id' => str_repeat('a', 64),
            'owner_token_hash' => hash('sha256', $forgedToken),
            'expires_at' => '2000-01-01 00:00:00',
            'fields' => ['tags' => ['speaker'], 'is_admin' => true],
            'is_admin' => true,
            'status' => 'approved',
        ], $this->phone())->assertCreated();

        $profile = DiscoveryProfile::sole();
        $this->assertSame($this->event->id, $profile->event_id);
        $this->assertNotSame(str_repeat('a', 64), $profile->discovery_id);
        $this->assertFalse($profile->ownerTokenMatches($forgedToken));
        $this->assertTrue($profile->ownerTokenMatches($created->json('owner_token')));
        $this->assertTrue($profile->expires_at->isFuture());
        $this->assertSame(['tags' => ['developer']], $profile->fields);

        $entry = $this->rosterEntry($this->event, 'Asha Rao');
        $share = $this->postJson(route('api.camp-card-share.store', $this->event), [
            'attendee_roster_id' => $entry->id, 'card' => ['role' => 'Dev', 'is_admin' => true, 'photo' => 'javascript:alert(1)'],
            'event_id' => $other->id, 'share_id' => str_repeat('b', 64), 'owner_token_hash' => hash('sha256', $forgedToken),
        ], $this->phone())->assertCreated();

        $card = SharedCampCard::sole();
        $this->assertSame($this->event->id, $card->event_id);
        $this->assertNotSame(str_repeat('b', 64), $card->share_id);
        $this->assertFalse($card->ownerTokenMatches($forgedToken));
        $this->assertSame(['role' => 'Dev'], $card->fields);
        $this->assertSame(['role' => 'Dev'], $share->json('card'));
    }
}
