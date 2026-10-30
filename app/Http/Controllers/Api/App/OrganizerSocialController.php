<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use App\Support\ManagerActivity;
use App\Support\QrKit;
use App\Support\SiteTheme;
use App\Support\SocialColors;
use App\Support\SocialForms;
use App\Support\SocialKit;
use App\Support\SocialPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An event's Social media page for the native app: the posts and people cards
 * (what each says, its caption and link — the app draws the picture), brand
 * colours, the WordCamp site's artwork, the QR kit and the Publish webhook.
 * The same helpers as the web page (SocialKit, SocialForms, SiteTheme…), for
 * the admin and the event's managers; a manager's change goes to the activity
 * log as it does from the web panel.
 */
class OrganizerSocialController extends Controller
{
    use ResolvesAppOrganizer;

    public function show(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $kit = SocialKit::data($event);
        $site = $kit['site'] ?? [];

        return response()->json(['data' => [
            'event' => $kit['event'] + ['short' => $kit['short']],
            'colors' => $kit['colors'],
            'colors_saved' => (bool) $kit['colorsSaved'],
            'posts' => collect($kit['posts'])->map(fn (array $p) => [
                'key' => $p['key'], 'label' => $p['label'] ?? $p['key'], 'kicker' => $p['kicker'] ?? '', 'headline' => $p['headline'] ?? '',
                'line' => $p['line'] ?? '', 'body' => $p['body'] ?? 'none', 'caption' => $p['caption'] ?? '', 'captions' => $p['captions'] ?? null, 'days' => $p['days'] ?? null,
                'picker' => $p['picker'] ?? null,
                'link' => SocialKit::link($event, $p['key']),
            ])->values(),
            'people' => collect($kit['people'])->map(fn (array $p) => [
                'id' => $p['id'], 'name' => $p['name'], 'photo' => $p['photo'], 'roles' => $p['role_labels'] ?? [],
                'role_keys' => $p['roles'] ?? [], 'has_card' => ! empty($p['card']), 'card_role' => $p['card']['role'] ?? null,
                'talk' => $p['talk'] ?? null, 'caption' => $p['caption'] ?? '', 'captions' => $p['captions'] ?? null, 'link' => $p['link'] ?? null,
            ])->values(),
            'speakers' => $kit['speakers'],
            'sponsors' => $kit['sponsors'],
            // Today / Spotlight pick a day or a session; times are already at the venue.
            'schedule' => [
                'days' => $kit['schedule']['days'],
                'next' => $kit['schedule']['next'] !== null ? (string) $kit['schedule']['next'] : null,
                'sessions' => collect($kit['schedule']['sessions'])->map(fn ($x) => [
                    'id' => (string) $x['id'], 'title' => $x['title'], 'day' => $x['day'], 'time' => $x['time'],
                    'track' => $x['track'], 'speakers' => $x['speakers'], 'photos' => $x['photos'],
                ])->values(),
            ],
            'tables' => $kit['tables'],
            'site' => [
                'colors' => collect($site['colors'] ?? [])->map(fn ($c) => is_array($c) ? ($c['hex'] ?? null) : $c)->filter(fn ($c) => is_string($c) && preg_match('/^#[0-9a-f]{6}$/i', $c))->values(),
                'tagline' => $site['tagline'] ?? null,
                'art' => array_values($site['art'] ?? []),
                'more' => array_values($site['more'] ?? []),
                'error' => $site['error'] ?? null,
            ],
            'qr' => QrKit::codes($event),
            'webhook' => ['set' => SocialPublisher::configured($event), 'masked' => SocialPublisher::configured($event) ? SocialPublisher::masked($event) : null],
        ]]);
    }

    public function colors(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $colors = SocialColors::save($request, $event);
        $this->record($request, $event, 'social', 'updated', 'Brand colours: '.implode(', ', $colors));

        return response()->json(['data' => $colors, 'message' => 'Brand colours saved.']);
    }

    /** Reads the WordCamp site's colours, fonts and artwork again. */
    public function site(Request $request, int $id): JsonResponse
    {
        $theme = SiteTheme::refresh($this->event($request, $id));

        return response()->json(['message' => $theme['error'] ?? 'Read the WordCamp site again: '.count($theme['colors']).' colours, '.count($theme['fonts']).' fonts, '.count($theme['art']).' pieces of artwork.']);
    }

    /** use | remove | kind on one piece of artwork. */
    public function art(Request $request, int $id, string $action): JsonResponse
    {
        $event = $this->event($request, $id);
        $data = $request->validate(['piece' => ['required', 'string', 'max:40'], 'kind' => [$action === 'kind' ? 'required' : 'nullable', 'in:'.implode(',', SiteTheme::KINDS)]]);

        $message = match ($action) {
            'use' => SiteTheme::use($event, $data['piece']),
            'remove' => SiteTheme::remove($event, $data['piece']),
            'kind' => SiteTheme::setKind($event, $data['piece'], $data['kind']),
        };
        $this->record($request, $event, 'social', $action === 'remove' ? 'removed' : 'updated', $action === 'remove' ? 'Artwork' : $message);

        return response()->json(['message' => $message]);
    }

    public function webhook(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $change = SocialForms::saveWebhook($request, $event);
        $this->record($request, $event, 'social', 'updated', $change);

        return response()->json(['message' => $change.'.']);
    }

    /** The card the app drew (PNG), its title and caption, to the event's webhook. */
    public function publish(Request $request, int $id): JsonResponse
    {
        $event = $this->event($request, $id);
        $reply = SocialForms::publish($request, $event, $this->who($request));
        if ($reply->getStatusCode() === 200) {
            $this->record($request, $event, 'social', 'added', 'Published '.ManagerActivity::quote((string) $request->input('kind')).' to the webhook');
        }

        return $reply;
    }
}
