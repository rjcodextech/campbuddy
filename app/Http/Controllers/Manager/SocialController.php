<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureEventManager;
use App\Support\ManagerActivity;
use App\Support\QrKit;
use App\Support\SocialColors;
use App\Support\SocialForms;
use App\Support\SocialImageProxy;
use App\Support\SocialKit;
use App\Support\SiteTheme;
use App\Support\SocialPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** An event manager's "Social media": the admin's page for their own events; changes logged. */
class SocialController extends Controller
{
    use ResolvesManagedEvent;

    public function index(Request $request, string $eventId): View
    {
        $event = $this->managedEvent($request, $eventId);

        return view('manager.events.social', [
            'event' => $event,
            'kit' => SocialKit::data($event),
            'codes' => QrKit::codes($event),
            'colorsUrl' => route('manager.events.social.colors', $event),
            'imageUrl' => route('manager.events.social.image', $event),
            'siteUrl' => route('manager.events.social.site', $event),
            'artUrl' => route('manager.events.social.art', $event),
            'artRemoveUrl' => route('manager.events.social.art.remove', $event),
            'artUseUrl' => route('manager.events.social.art.use', $event),
            'artKindUrl' => route('manager.events.social.art.kind', $event),
            'publishUrl' => SocialPublisher::configured($event) ? route('manager.events.social.publish', $event) : null,
            'webhookUrl' => route('manager.events.social.webhook', $event),
            'webhookSet' => SocialPublisher::configured($event),
            'remindersUrl' => route('manager.social.reminders'),
            'remindersOn' => (bool) $request->user(EnsureEventManager::GUARD)?->social_reminders,
            'remindersPhones' => \App\Models\OrganizerPushToken::where('role', 'manager')->where('user_id', $request->user(EnsureEventManager::GUARD)?->id)->count(),
        ]);
    }

    /** Turns "Remind me on my phone when a post is due" on or off for the signed-in manager. */
    public function reminders(Request $request): RedirectResponse
    {
        $manager = $request->user(EnsureEventManager::GUARD);
        $manager->forceFill(['social_reminders' => $request->boolean('on')])->save();

        return back()->with('status', $manager->social_reminders ? 'Post reminders are on for your phone.' : 'Post reminders are off.');
    }

    public function colors(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $colors = SocialColors::save($request, $event);
        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'social', 'updated', 'Brand colours: '.implode(', ', $colors));

        return redirect()->route('manager.events.social', $event)->with('status', 'Brand colours saved.');
    }

    public function webhook(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $change = SocialForms::saveWebhook($request, $event);
        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'social', 'updated', $change);

        return redirect()->route('manager.events.social', $event)->with('status', $change.'.');
    }

    public function publish(Request $request, string $eventId): JsonResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $manager = $request->user(EnsureEventManager::GUARD);
        $reply = SocialForms::publish($request, $event, 'manager: '.$manager->name);
        if ($reply->getStatusCode() === 200) {
            ManagerActivity::record($manager, $event, 'social', 'added', 'Published '.ManagerActivity::quote((string) $request->input('kind')).' to the webhook');
        }

        return $reply;
    }

    /** A WordCamp-hosted image (sponsor logo) served from here, so the page's canvas may use it. */
    public function image(Request $request, string $eventId): Response
    {
        $this->managedEvent($request, $eventId);

        return SocialImageProxy::respond((string) $request->query('u'));
    }

    /** Reads the WordCamp site's colours, fonts and artwork again. */
    public function site(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $theme = SiteTheme::refresh($event);

        return redirect()->route('manager.events.social', $event)->with('status', $theme['error'] ?? 'Read the WordCamp site again: '.count($theme['colors']).' colours, '.count($theme['fonts']).' fonts, '.count($theme['art']).' pieces of artwork.');
    }

    /** Artwork uploaded into an empty slot. */
    public function art(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        [$file, $kind] = SocialForms::artwork($request);
        $status = SiteTheme::upload($event, $file, $kind);
        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'social', 'added', 'Artwork '.ManagerActivity::quote($file->getClientOriginalName()));

        return redirect()->route('manager.events.social', $event)->with('status', $status);
    }

    /** Takes a piece of artwork off (an uploaded one is deleted). */
    public function removeArt(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $status = SiteTheme::remove($event, (string) $request->input('piece'));
        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'social', 'removed', 'Artwork');

        return redirect()->route('manager.events.social', $event)->with('status', $status);
    }

    /** Puts another piece of the site's artwork in use. */
    public function useArt(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $status = SiteTheme::use($event, (string) $request->input('piece'));
        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'social', 'updated', $status);

        return redirect()->route('manager.events.social', $event)->with('status', $status);
    }

    /** Says what a piece of artwork is (pattern, illustration, photo). */
    public function artKind(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);
        $status = SiteTheme::setKind($event, (string) $request->input('piece'), (string) $request->input('kind'));
        ManagerActivity::record($request->user(EnsureEventManager::GUARD), $event, 'social', 'updated', $status);

        return redirect()->route('manager.events.social', $event)->with('status', $status);
    }

    /** The old "QR codes" tab now lives on this page. */
    public function qr(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);

        return redirect()->to(route('manager.events.social', $event).'#qr');
    }
}
