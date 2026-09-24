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

    private function send(array $me, array $to, string $body)
    {
        return $this->withToken($me['token'])->postJson(route('api.discovery.messages.store', [$this->event, $me['id']]), ['to' => $to['id'], 'body' => $body]);
    }

    public function test_two_anonymous_attendees_swap_names_only_when_both_wave(): void
    {
        $asha = $this->join();
        $ben = $this->join();

        $this->wave($asha, $ben, ['name' => 'Asha', 'message' => 'By the coffee stand at 11?'])->assertCreated()
            ->assertJson(['sent' => [$ben['id']], 'received' => [], 'mutual' => [], 'pending' => [['discovery_id' => $ben['id'], 'messages' => [['mine' => true, 'body' => 'By the coffee stand at 11?']]]]]);

        // Ben learns only that this (anonymous) match waved and wrote — not the name or the words.
        $this->waves($ben)->assertOk()->assertJson(['received' => [$asha['id']], 'received_with_message' => [$asha['id']], 'mutual' => []]);
        $this->assertStringNotContainsString('Asha', $this->waves($ben)->getContent());
        $this->assertStringNotContainsString('coffee', $this->waves($ben)->getContent());

        $this->wave($ben, $asha, ['name' => 'Ben', 'message' => 'See you there!'])->assertCreated();

        $this->waves($ben)->assertJson(['mutual' => [[
            'discovery_id' => $asha['id'],
            'name' => 'Asha',
            'messages' => [['mine' => false, 'body' => 'By the coffee stand at 11?'], ['mine' => true, 'body' => 'See you there!']],
            'mine' => 1, 'theirs' => 1, 'can_send' => false, 'reason' => 'waiting',
        ]]]);
        $this->waves($asha)->assertJson(['mutual' => [['name' => 'Ben', 'mine' => 1, 'can_send' => true, 'reason' => null]]]);

        // The public list still shows both as anonymous, with no messages.
        $public = $this->getJson(route('api.discovery.index', $this->event))->json('data');
        $this->assertSame([null, null], array_column($public, 'name'));
        $this->assertStringNotContainsString('coffee', json_encode($public));
    }

    public function test_messages_take_turns_and_stop_at_three_each(): void
    {
        [$asha, $ben] = $this->mutual();

        $this->send($asha, $ben, 'One')->assertCreated();
        // Not again until Ben replies.
        $this->send($asha, $ben, 'Two, too soon')->assertUnprocessable()->assertJsonValidationErrors(['body' => 'Wait for their reply']);

        $this->send($ben, $asha, 'Reply one')->assertCreated();
        $this->send($asha, $ben, 'Two')->assertCreated();
        $this->send($ben, $asha, 'Reply two')->assertCreated();
        $this->send($asha, $ben, 'Three')->assertCreated()->assertJson(['mutual' => [['mine' => 3, 'can_send' => false, 'reason' => 'limit']]]);
        $this->send($ben, $asha, 'Reply three')->assertCreated();

        // Three each is the end — even with Ben having replied.
        $this->send($asha, $ben, 'Four')->assertUnprocessable()->assertJsonValidationErrors(['body' => 'swap Camp Cards']);
        $this->send($ben, $asha, 'Four')->assertUnprocessable();

        $this->waves($asha)->assertJsonCount(6, 'mutual.0.messages')->assertJson(['max_messages' => 3]);
    }

    public function test_no_messages_before_both_have_waved(): void
    {
        $asha = $this->join();
        $ben = $this->join();
        $this->wave($asha, $ben, ['name' => 'Asha']);

        $this->send($asha, $ben, 'Hello?')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->send($ben, $asha, 'Hi')->assertUnprocessable();
    }

    public function test_a_wave_cannot_be_resent_to_sneak_in_more_messages(): void
    {
        $asha = $this->join();
        $ben = $this->join();
        $this->wave($asha, $ben, ['name' => 'Asha', 'message' => 'First'])->assertCreated();

        $this->wave($asha, $ben, ['name' => 'Asha', 'message' => 'Second'])->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->assertSame(['First'], \App\Models\DiscoveryMessage::pluck('body')->all());
    }

    public function test_messages_are_plain_short_text(): void
    {
        [$asha, $ben] = $this->mutual();

        $this->send($asha, $ben, "  Hi\u{0000}\n\n   there \u{202E}  ")->assertCreated();
        $this->assertSame('Hi there', \App\Models\DiscoveryMessage::latest('id')->value('body'));

        $this->send($ben, $asha, str_repeat('x', 141))->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->send($ben, $asha, "   \n ")->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_taking_a_wave_back_ends_the_conversation(): void
    {
        [$asha, $ben] = $this->mutual();
        $this->send($asha, $ben, 'Hi')->assertCreated();

        $this->withToken($asha['token'])->deleteJson(route('api.discovery.waves.destroy', [$this->event, $asha['id'], $ben['id']]))->assertOk();

        $this->assertSame(0, \App\Models\DiscoveryMessage::count());
        $this->waves($ben)->assertJson(['mutual' => [], 'received' => []]);
    }

    public function test_someone_else_cannot_read_or_send_in_a_conversation(): void
    {
        [$asha, $ben] = $this->mutual();
        $this->send($asha, $ben, 'Private plan');
        $eve = $this->join();

        $this->withToken($eve['token'])->postJson(route('api.discovery.messages.store', [$this->event, $asha['id']]), ['to' => $ben['id'], 'body' => 'x'])->assertForbidden();
        $this->send($eve, $ben, 'Hi')->assertUnprocessable();
        $this->assertStringNotContainsString('Private plan', $this->waves($eve)->getContent());
    }

    /** @return array{0: array, 1: array} two people who have both waved */
    private function mutual(): array
    {
        $asha = $this->join();
        $ben = $this->join();
        $this->wave($asha, $ben, ['name' => 'Asha'])->assertCreated();
        $this->wave($ben, $asha, ['name' => 'Ben'])->assertCreated();

        return [$asha, $ben];
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
