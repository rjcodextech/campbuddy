<?php

namespace Tests\Feature;

use App\Models\DiscoveryWave;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Waving: two matches — even anonymous ones — can swap names only by both
 * waving; nobody else ever sees a name or who waved at whom.
 */
class DiscoveryWaveTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        $this->event = Event::withoutEvents(fn () => Event::create([
            'slug' => 'wc-test', 'display_name' => 'WordCamp Test 2026', 'source_site_url' => 'https://test.wordcamp.org/2026',
            'status' => 'active', 'is_visible' => true,
        ]));
    }

    /** @return array{id: string, token: string} */
    private function join(array $body = []): array
    {
        $card = $this->postJson(route('api.discovery.store', $this->event), $body + ['tags' => ['business owner']])->assertCreated()->json();

        return ['id' => $card['discovery_id'], 'token' => $card['owner_token']];
    }

    private function wave(array $me, array $to, array $extra = [])
    {
        return $this->withToken($me['token'])->postJson(route('api.discovery.waves.store', [$this->event, $me['id']]), ['to' => $to['id']] + $extra);
    }

    private function waves(array $me)
    {
        return $this->withToken($me['token'])->getJson(route('api.discovery.waves.index', [$this->event, $me['id']]));
    }

    public function test_two_anonymous_attendees_swap_names_only_when_both_wave(): void
    {
        $asha = $this->join();
        $ben = $this->join();

        $this->wave($asha, $ben, ['name' => 'Asha', 'message' => 'By the coffee stand at 11?'])->assertCreated()
            ->assertJson(['sent' => [$ben['id']], 'received' => [], 'mutual' => []]);

        // Ben learns only that this (anonymous) match waved — no name yet.
        $this->waves($ben)->assertOk()->assertExactJson(['sent' => [], 'received' => [$asha['id']], 'mutual' => []]);
        $this->assertStringNotContainsString('Asha', $this->waves($ben)->getContent());

        $this->wave($ben, $asha, ['name' => 'Ben', 'message' => 'See you there!'])->assertCreated();

        $this->waves($ben)->assertJson(['mutual' => [['discovery_id' => $asha['id'], 'name' => 'Asha', 'message' => 'By the coffee stand at 11?', 'my_message' => 'See you there!']]]);
        $this->waves($asha)->assertJson(['mutual' => [['discovery_id' => $ben['id'], 'name' => 'Ben', 'message' => 'See you there!']]]);

        // The public list still shows both as anonymous.
        $public = $this->getJson(route('api.discovery.index', $this->event))->json('data');
        $this->assertSame([null, null], array_column($public, 'name'));
        $this->assertStringNotContainsString('coffee', json_encode($public));
    }

    public function test_an_anonymous_profile_must_give_a_name_to_wave(): void
    {
        $asha = $this->join();
        $ben = $this->join();

        $this->wave($asha, $ben)->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_a_named_profile_waves_under_its_own_name(): void
    {
        $asha = $this->join(['display_name' => 'Asha Rao']);
        $ben = $this->join();

        $this->wave($asha, $ben)->assertCreated();
        $this->wave($ben, $asha, ['name' => 'Ben'])->assertCreated();

        $this->waves($ben)->assertJson(['mutual' => [['name' => 'Asha Rao']]]);
    }

    public function test_waves_need_the_owner_token_and_stay_private(): void
    {
        $asha = $this->join();
        $ben = $this->join();

        $this->withToken('wrong')->getJson(route('api.discovery.waves.index', [$this->event, $asha['id']]))->assertForbidden();
        $this->withToken($ben['token'])->postJson(route('api.discovery.waves.store', [$this->event, $asha['id']]), ['to' => $ben['id'], 'name' => 'x'])->assertForbidden();
        $this->wave($asha, $asha, ['name' => 'Asha'])->assertUnprocessable();
    }

    public function test_undo_and_leaving_remove_waves(): void
    {
        $asha = $this->join();
        $ben = $this->join();
        $this->wave($asha, $ben, ['name' => 'Asha']);

        $this->withToken($asha['token'])->deleteJson(route('api.discovery.waves.destroy', [$this->event, $asha['id'], $ben['id']]))
            ->assertOk()->assertJson(['sent' => []]);
        $this->waves($ben)->assertJson(['received' => []]);

        $this->wave($asha, $ben, ['name' => 'Asha']);
        $this->withToken($asha['token'])->deleteJson(route('api.discovery.destroy', [$this->event, $asha['id']]))->assertNoContent();

        $this->assertSame(0, DiscoveryWave::count());
    }
}
