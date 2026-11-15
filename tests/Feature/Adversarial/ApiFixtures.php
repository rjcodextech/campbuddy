<?php

namespace Tests\Feature\Adversarial;

use App\Models\AttendeeRoster;
use App\Models\Event;
use App\Models\EventManager;
use App\Models\Offer;
use App\Support\AppOrganizer;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Shared set-up for the adversarial API tests: events in a known state, an
 * attendee list, discovery profiles with their owner tokens, and organizer
 * bearer tokens. Nothing here is a real credential.
 */
trait ApiFixtures
{
    protected function makeEvent(string $slug = 'wc-attack', array $attributes = []): Event
    {
        return Event::withoutEvents(fn () => Event::create($attributes + [
            'slug' => $slug,
            'display_name' => 'WordCamp '.Str::headline($slug),
            'source_site_url' => "https://{$slug}.wordcamp.org/2026",
            'status' => 'active',
            'is_visible' => true,
            'starts_on' => '2026-11-20',
            'ends_on' => '2026-11-21',
            'timezone' => 'Asia/Kolkata',
        ]));
    }

    protected function rosterEntry(Event $event, string $name, array $attributes = []): AttendeeRoster
    {
        return AttendeeRoster::create($attributes + [
            'event_id' => $event->id,
            'name' => $name,
            'gravatar_url' => null,
            'links' => [],
            'content_hash' => hash('sha256', $event->id.$name),
            'is_suppressed' => false,
        ]);
    }

    protected function leadOffer(Event $event): Offer
    {
        return Offer::create([
            'event_id' => $event->id, 'title' => 'Deal', 'description' => 'd', 'url' => 'https://example.com',
            'is_active' => true, 'capture_leads' => true,
        ]);
    }

    /** @return array{discovery_id: string, owner_token: string} */
    protected function joinDiscovery(Event $event, array $body = []): array
    {
        $reply = $this->postJson(route('api.discovery.store', $event), $body + ['tags' => ['developer']], $this->phone())->assertCreated();

        return ['discovery_id' => $reply->json('discovery_id'), 'owner_token' => $reply->json('owner_token')];
    }

    /** A fresh, well-formed anonymous phone id (so each call gets the per-phone allowance). */
    protected function phone(): array
    {
        return ['X-CampBuddy-Device' => (string) Str::uuid()];
    }

    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    protected function organizerToken(string $role, int $id): string
    {
        return AppOrganizer::issue($role, $id, 'test phone')['token'];
    }

    protected function managerOf(Event ...$events): EventManager
    {
        $manager = EventManager::factory()->create();
        foreach ($events as $event) {
            $manager->events()->attach($event->id);
        }

        return $manager;
    }

    /** base64url text of the shape the device-transfer endpoints expect. */
    protected function base64url(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    /** Never a stack trace, a file path or a framework class name in an error body. */
    protected function assertNoDebugLeak(TestResponse $response): void
    {
        $body = (string) $response->getContent();

        foreach (['Stack trace', '#0 ', 'vendor/laravel', 'vendor\\laravel', '"exception"', '"trace"', 'Illuminate\\', 'SQLSTATE', '.php'] as $leak) {
            $this->assertStringNotContainsString($leak, $body, "The response leaks debug information ({$leak}).");
        }
    }
}
