<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Models\Offer;
use App\Models\SponsorDealSubmission;
use App\Support\DataVersion;
use App\Support\EventData;
use App\Support\PersonalDataErasure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Manage submitted sponsor deals in the admin console.
 * Review authentic submissions, approve them into event offers, or delete invalid ones.
 */
class SponsorDealSubmissionController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'all'], true)) {
            $status = 'pending';
        }

        $query = SponsorDealSubmission::with(['event', 'approvedOffer'])->latest();

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        $submissions = $query->paginate(25)->withQueryString();

        // Bulk-prime sponsors cache to prevent N+1 queries when checking isOfficialSponsor
        $eventIds = $submissions->pluck('event_id')->unique()->filter()->values()->all();
        EventData::getMany($eventIds, 'sponsors');

        $pendingCount = SponsorDealSubmission::pending()->count();
        $approvedCount = SponsorDealSubmission::approved()->count();
        $totalCount = SponsorDealSubmission::count();

        return view('admin.sponsor-deals.index', compact(
            'submissions',
            'status',
            'pendingCount',
            'approvedCount',
            'totalCount'
        ));
    }

    public function show(SponsorDealSubmission $submission): View
    {
        $submission->load(['event', 'approvedOffer']);

        return view('admin.sponsor-deals.show', compact('submission'));
    }

    /**
     * Approve submission and move it into the event's deals table.
     * Can be saved as draft (inactive) or published immediately as active.
     */
    public function approve(Request $request, SponsorDealSubmission $submission): RedirectResponse
    {
        $event = $submission->event;
        if (! $event) {
            return back()->withErrors(['event' => 'Associated event no longer exists.']);
        }

        $publish = $request->boolean('publish', false);

        $mediaAssetId = null;
        if ($submission->logo_path && Storage::disk('public')->exists($submission->logo_path)) {
            $mediaAsset = MediaAsset::create([
                'disk' => 'public',
                'path' => $submission->logo_path,
                'filename' => basename($submission->logo_path),
                'mime_type' => 'image/png',
                'size' => Storage::disk('public')->size($submission->logo_path),
                'uploaded_by' => auth()->id(),
            ]);
            $mediaAssetId = $mediaAsset->id;
        }

        $offer = $event->offers()->create([
            'brand' => $submission->displayBrand(),
            'website' => $submission->website,
            'title' => $submission->title,
            'highlight' => $submission->highlight,
            'description' => $submission->description,
            'terms' => $submission->terms,
            'coupon_code' => $submission->coupon_code,
            'url' => $submission->url,
            'cta_label' => $submission->cta_label ?: 'Get the deal',
            'icon' => $submission->icon ?: '🏷',
            'media_asset_id' => $mediaAssetId,
            'is_active' => $publish,
            'sort_order' => ($event->offers()->max('sort_order') ?? 0) + 10,
        ]);

        $submission->update([
            'status' => 'approved',
            'approved_offer_id' => $offer->id,
        ]);

        DataVersion::forget($event->id);

        $statusText = $publish ? 'published as active' : 'saved as draft';

        return redirect()->route('admin.deal-submissions.index')
            ->with('status', "Deal for “{$submission->displayBrand()}” approved and {$statusText} under {$event->display_name}.");
    }

    /**
     * Delete an invalid or rejected deal submission from the staging table.
     */
    public function destroy(SponsorDealSubmission $submission): RedirectResponse
    {
        $brand = $submission->displayBrand();

        // An approved deal still shows this logo (it is in the media library): keep the file then.
        if ($submission->logo_path) {
            PersonalDataErasure::deleteUnusedLogos([$submission->logo_path]);
        }

        $submission->delete();

        return redirect()->route('admin.deal-submissions.index')
            ->with('status', "Deal submission for “{$brand}” has been completely deleted.");
    }
}
