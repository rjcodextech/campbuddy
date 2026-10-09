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
            'publishUrl' => SocialPublisher::configured($event) ? route('manager.events.social.publish', $event) : null,
            'webhookUrl' => route('manager.events.social.webhook', $event),
            'webhookSet' => SocialPublisher::configured($event),
        ]);
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

    /** The old "QR codes" tab now lives on this page. */
    public function qr(Request $request, string $eventId): RedirectResponse
    {
        $event = $this->managedEvent($request, $eventId);

        return redirect()->to(route('manager.events.social', $event).'#qr');
    }
}
