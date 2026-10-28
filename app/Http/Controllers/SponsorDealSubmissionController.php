<?php

namespace App\Http\Controllers;

use App\Mail\SponsorDealSubmittedMail;
use App\Http\Requests\StoreSponsorDealSubmissionRequest;
use App\Models\Event;
use App\Models\SponsorDealSubmission;
use App\Support\EventData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Public, unlisted form where WordCamp sponsors submit attendee deals.
 * Submissions are held in a temporary staging table for admin verification.
 */
class SponsorDealSubmissionController extends Controller
{
    /**
     * Show the deal submission form. Not linked in public navigation.
     */
    public function create(): View
    {
        $events = Event::query()
            ->whereIn('status', ['active', 'approved'])
            ->whereNotNull('starts_on')
            ->orderBy('starts_on')
            ->get(['id', 'display_name', 'short_name', 'starts_on', 'ends_on', 'slug', 'source_site_url']);

        $submissionToken = Crypt::encryptString(json_encode([
            'time' => now()->timestamp,
            'ip' => request()->ip(),
        ]));

        return view('sponsor.deal-submission', compact('events', 'submissionToken'));
    }

    /**
     * Fetch ingested sponsors for a given event as JSON.
     */
    public function sponsors(Event $event): JsonResponse
    {
        $sponsors = EventData::get($event->id, 'sponsors') ?? [];

        $list = collect($sponsors)
            ->filter(fn ($s) => is_array($s) && filled($s['name'] ?? null))
            ->map(fn ($s) => [
                'name' => trim((string) $s['name']),
                'website' => $s['website'] ?? null,
                'logo_url' => $s['logo_url'] ?? null,
            ])
            ->unique('name')
            ->values();

        return response()->json([
            'event' => [
                'id' => $event->id,
                'name' => $event->display_name,
            ],
            'sponsors' => $list,
        ]);
    }

    /**
     * Process and stage a sponsor deal submission.
     */
    public function store(StoreSponsorDealSubmissionRequest $request): RedirectResponse
    {
        // 1. Honeypot check (hidden field bots populate)
        if (filled($request->input('hp_organization_token'))) {
            // Silently discard bot submission while returning success
            return redirect()->route('sponsor-deals.success');
        }

        // 2. Anti-bot speed & expiration check
        try {
            $tokenData = json_decode(Crypt::decryptString($request->input('submission_token')), true);
            $elapsed = now()->timestamp - ($tokenData['time'] ?? 0);

            if ($elapsed < 3) {
                return back()->withInput()->withErrors([
                    'deal_form' => 'The form was submitted too fast. Please take a moment and submit again.',
                ]);
            }

            if ($elapsed > 7200) {
                return back()->withInput()->withErrors([
                    'deal_form' => 'Your submission session timed out. Please refresh the page and submit again.',
                ]);
            }
        } catch (\Throwable) {
            return back()->withInput()->withErrors([
                'deal_form' => 'Security token invalid or expired. Please refresh the page.',
            ]);
        }

        $data = $request->safe()->except(['submission_token', 'hp_organization_token', 'logo_image']);
        $data['brand'] = filled($data['brand'] ?? null) ? $data['brand'] : $data['sponsor_name'];
        $data['icon'] = filled($data['icon'] ?? null) ? $data['icon'] : '🏷';

        // 3. Handle uploaded PNG logo (max 100 KB)
        if ($request->hasFile('logo_image')) {
            $file = $request->file('logo_image');
            if ($file->isValid()) {
                $path = $file->store('sponsor-deals/logos', 'public');
                $data['logo_path'] = $path;
                $data['logo_url'] = Storage::disk('public')->url($path);
            }
        }

        // Try to autofill logo from event ingested sponsors if not uploaded or set
        if (blank($data['logo_url'] ?? null)) {
            $sponsors = EventData::get((int) $data['event_id'], 'sponsors') ?? [];
            $search = mb_strtolower(trim((string) $data['sponsor_name']));

            foreach ($sponsors as $s) {
                if (is_array($s) && mb_strtolower(trim((string) ($s['name'] ?? ''))) === $search && filled($s['logo_url'] ?? null)) {
                    $data['logo_url'] = $s['logo_url'];
                    break;
                }
            }
        }

        $data['status'] = 'pending';
        $data['ip_address'] = $request->ip();
        $data['user_agent'] = substr((string) $request->userAgent(), 0, 500);

        $submission = SponsorDealSubmission::create($data);

        // 3. Trigger email notification to hello@wpsimplified.in
        try {
            Mail::to('hello@wpsimplified.in')->send(new SponsorDealSubmittedMail($submission));
        } catch (\Throwable $e) {
            Log::warning('Sponsor deal submission email failed to send: '.$e->getMessage());
        }

        return redirect()->route('sponsor-deals.success')->with('submitted_sponsor', $submission->sponsor_name);
    }

    /**
     * Confirmation thank-you screen after submitting a deal.
     */
    public function success(): View
    {
        return view('sponsor.deal-submission-success');
    }
}
