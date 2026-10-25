<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\QrKit;
use App\Support\SocialColors;
use App\Support\SocialForms;
use App\Support\SocialImageProxy;
use App\Support\SocialKit;
use App\Support\SiteTheme;
use App\Support\SocialPublisher;
use App\Support\StudioMusic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Admin → Event → Social media: posts, people cards, brand colours, QR codes, Publish. */
class SocialController extends Controller
{
    public function index(Event $event): View
    {
        Gate::authorize('viewAny', Event::class);

        return view('admin.events.social', [
            'event' => $event,
            'kit' => SocialKit::data($event),
            'codes' => QrKit::codes($event),
            'colorsUrl' => route('admin.events.social.colors', $event),
            'imageUrl' => route('admin.events.social.image', $event),
            'musicUrl' => route('admin.events.social.music', $event),
            'studioTracks' => StudioMusic::tracks(),
            'siteUrl' => route('admin.events.social.site', $event),
            'artUrl' => route('admin.events.social.art', $event),
            'artRemoveUrl' => route('admin.events.social.art.remove', $event),
            'artUseUrl' => route('admin.events.social.art.use', $event),
            'artKindUrl' => route('admin.events.social.art.kind', $event),
            'publishUrl' => SocialPublisher::configured($event) ? route('admin.events.social.publish', $event) : null,
            'webhookUrl' => route('admin.events.social.webhook', $event),
            'webhookSet' => SocialPublisher::configured($event),
            'remindersUrl' => route('admin.social.reminders'),
            'remindersOn' => (bool) auth()->user()?->social_reminders,
            'remindersPhones' => \App\Models\OrganizerPushToken::where('role', 'admin')->where('user_id', auth()->id())->count(),
        ]);
    }

    /** Turns "Remind me on my phone when a post is due" on or off for the signed-in admin. */
    public function reminders(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill(['social_reminders' => $request->boolean('on')])->save();

        return back()->with('status', $user->social_reminders ? 'Post reminders are on for your phone.' : 'Post reminders are off.');
    }

    public function colors(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        SocialColors::save($request, $event);

        return redirect()->route('admin.events.social', $event)->with('status', 'Brand colours saved.');
    }

    public function webhook(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        return redirect()->route('admin.events.social', $event)->with('status', SocialForms::saveWebhook($request, $event).'.');
    }

    public function publish(Request $request, Event $event): JsonResponse
    {
        Gate::authorize('update', $event);

        return SocialForms::publish($request, $event, 'admin: '.$request->user()->name);
    }

    /** A WordCamp-hosted image (sponsor logo) served from here, so the page's canvas may use it. */
    public function image(Request $request, Event $event): Response
    {
        Gate::authorize('viewAny', Event::class);

        return SocialImageProxy::respond((string) $request->query('u'));
    }

    /** A track from the shared Studio music library, for the reels. */
    public function music(Request $request, Event $event): Response
    {
        Gate::authorize('viewAny', Event::class);

        return StudioMusic::respond((string) $request->query('f'));
    }

    /** Reads the WordCamp site's colours, fonts and artwork again. */
    public function site(Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        $theme = SiteTheme::refresh($event);

        return redirect()->route('admin.events.social', $event)->with('status', $theme['error'] ?? 'Read the WordCamp site again: '.count($theme['colors']).' colours, '.count($theme['fonts']).' fonts, '.count($theme['art']).' pieces of artwork.');
    }

    /** Artwork uploaded into an empty slot. */
    public function art(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);
        [$file, $kind] = SocialForms::artwork($request);

        return redirect()->route('admin.events.social', $event)->with('status', SiteTheme::upload($event, $file, $kind));
    }

    /** Takes a piece of artwork off (an uploaded one is deleted). */
    public function removeArt(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        return redirect()->route('admin.events.social', $event)->with('status', SiteTheme::remove($event, (string) $request->input('piece')));
    }

    /** Puts another piece of the site's artwork in use. */
    public function useArt(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        return redirect()->route('admin.events.social', $event)->with('status', SiteTheme::use($event, (string) $request->input('piece')));
    }

    /** Says what a piece of artwork is (pattern, illustration, photo). */
    public function artKind(Request $request, Event $event): RedirectResponse
    {
        Gate::authorize('update', $event);

        return redirect()->route('admin.events.social', $event)->with('status', SiteTheme::setKind($event, (string) $request->input('piece'), (string) $request->input('kind')));
    }

    /** The old "QR codes" tab now lives on this page. */
    public function qr(Event $event): RedirectResponse
    {
        return redirect()->to(route('admin.events.social', $event).'#qr');
    }
}
