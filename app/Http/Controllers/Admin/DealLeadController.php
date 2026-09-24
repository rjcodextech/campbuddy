<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\OfferLead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leads captured via Offer::capture_leads (§7) — Name/Email/Mobile an
 * attendee submits before opening a deal with lead-capture enabled.
 * Event-scoped and filterable the same way Roster is, plus a CSV export.
 */
class DealLeadController extends Controller
{
    public function index(Request $request, Event $event): View
    {
        Gate::authorize('viewAny', OfferLead::class);

        $offers = $event->offers()->orderBy('title')->get(['id', 'title']);

        $leads = $this->filteredQuery($request, $event)
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.deal-leads.index', [
            'event' => $event,
            'offers' => $offers,
            'leads' => $leads,
            'filters' => $request->only(['offer_id', 'from', 'to']),
        ]);
    }

    public function export(Request $request, Event $event): StreamedResponse
    {
        Gate::authorize('viewAny', OfferLead::class);

        $leads = $this->filteredQuery($request, $event)->latest()->get();

        $filename = 'deal-leads-'.$event->slug.'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($leads) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Deal', 'Name', 'Email', 'Mobile', 'Submitted at']);

            foreach ($leads as $lead) {
                fputcsv($handle, [
                    $this->csvSafe($lead->offer?->title),
                    $this->csvSafe($lead->name),
                    $this->csvSafe($lead->email),
                    $this->csvSafe($lead->mobile),
                    $lead->created_at->toDateTimeString(),
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * These cells hold whatever an anonymous visitor typed, and a spreadsheet
     * runs a cell that starts with = + - @ (or a tab / carriage return) as a
     * formula — so an admin opening the export could be running a stranger's
     * formula. A leading apostrophe makes it plain text. Phone-style values
     * ("+91 98765 43210") are just digits and are left alone.
     */
    private function csvSafe(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $looksLikeFormula = preg_match('/^[=+\-@\t\r]/', $value) === 1;
        $isPlainNumber = preg_match('/^[+\-]?[0-9][0-9 ().\-]*$/', $value) === 1;

        return $looksLikeFormula && ! $isPlainNumber ? "'".$value : $value;
    }

    private function filteredQuery(Request $request, Event $event): Builder
    {
        return OfferLead::where('event_id', $event->id)
            ->with('offer:id,title')
            ->when($request->filled('offer_id'), fn ($q) => $q->where('offer_id', $request->integer('offer_id')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')));
    }
}
