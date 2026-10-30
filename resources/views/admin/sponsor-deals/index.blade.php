<x-app-layout title="Sponsor Deal Submissions" subtitle="Review and verify offers submitted by WordCamp sponsors before they go live"
              :breadcrumbs="[['Deals & offers'], ['Deal submissions']]">

    <div class="max-w-6xl space-y-6">
        {{-- Navigation tabs --}}
        <div class="flex items-center justify-between border-b border-line pb-4">
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.deal-submissions.index', ['status' => 'pending']) }}"
                   @class([
                       'inline-flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-sm font-medium transition-colors',
                       'bg-maroon-text text-white shadow-2xs' => $status === 'pending',
                       'text-muted hover:bg-paper-soft hover:text-ink' => $status !== 'pending',
                   ])>
                    <span>Pending Review</span>
                    @if ($pendingCount > 0)
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-bold',
                            'bg-white text-maroon-text' => $status === 'pending',
                            'bg-gold/20 text-gold-dark' => $status !== 'pending',
                        ])>{{ $pendingCount }}</span>
                    @endif
                </a>

                <a href="{{ route('admin.deal-submissions.index', ['status' => 'approved']) }}"
                   @class([
                       'inline-flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-sm font-medium transition-colors',
                       'bg-maroon-text text-white shadow-2xs' => $status === 'approved',
                       'text-muted hover:bg-paper-soft hover:text-ink' => $status !== 'approved',
                   ])>
                    <span>Approved</span>
                    <span class="rounded-full bg-paper px-2 py-0.5 text-xs text-muted">{{ $approvedCount }}</span>
                </a>

                <a href="{{ route('admin.deal-submissions.index', ['status' => 'all']) }}"
                   @class([
                       'inline-flex items-center gap-2 rounded-lg px-3.5 py-1.5 text-sm font-medium transition-colors',
                       'bg-maroon-text text-white shadow-2xs' => $status === 'all',
                       'text-muted hover:bg-paper-soft hover:text-ink' => $status !== 'all',
                   ])>
                    <span>All Submissions</span>
                    <span class="rounded-full bg-paper px-2 py-0.5 text-xs text-muted">{{ $totalCount }}</span>
                </a>
            </div>

            <div class="text-xs text-muted">
                <a href="{{ route('sponsor-deals.create') }}" target="_blank" class="inline-flex items-center gap-1.5 text-maroon-text hover:underline font-medium">
                    <x-icon name="external" class="h-3.5 w-3.5" />
                    Open Submission Form
                </a>
            </div>
        </div>

        <x-alert type="info">
            Sponsors submit offers via the unlisted portal. They are staged here so you can verify that the submission is
            genuine (e.g. check corporate email domain and sponsor list match) before moving it into an event's active deals.
            Submissions can be <strong>approved as a draft</strong>, <strong>published immediately</strong>, or <strong>permanently deleted</strong> if spam.
        </x-alert>

        <x-card title="Submissions" flush>
            @if ($submissions->isEmpty())
                <div class="px-5 py-12 text-center sm:px-6">
                    <x-icon name="inbox" class="mx-auto h-8 w-8 text-muted/50" />
                    <p class="mt-2 text-sm font-medium">No {{ $status }} deal submissions</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-muted">
                        @if ($status === 'pending')
                            All submitted sponsor offers have been reviewed!
                        @else
                            No submissions found in this category.
                        @endif
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-paper-soft text-xs uppercase tracking-wider text-muted border-b border-line">
                            <tr>
                                <th class="px-5 py-3.5 font-semibold">Date</th>
                                <th class="px-5 py-3.5 font-semibold">Event & Sponsor</th>
                                <th class="px-5 py-3.5 font-semibold">Deal Title</th>
                                <th class="px-5 py-3.5 font-semibold">Submitter & Verification</th>
                                <th class="px-5 py-3.5 font-semibold">Status</th>
                                <th class="px-5 py-3.5 font-semibold text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($submissions as $sub)
                                <tr class="hover:bg-paper-soft/50 transition-colors">
                                    <td class="px-5 py-4 whitespace-nowrap text-xs text-muted">
                                        {{ $sub->created_at->format('j M Y') }}<br>
                                        <span class="text-[11px] text-muted/70">{{ $sub->created_at->format('g:i A') }}</span>
                                    </td>

                                    <td class="px-5 py-4">
                                        <div class="font-semibold text-ink">
                                            {{ $sub->displayBrand() }}
                                        </div>
                                        <div class="text-xs text-maroon-text font-medium">
                                            {{ $sub->event?->display_name ?? '—' }}
                                        </div>
                                        @if ($sub->isOfficialSponsor())
                                            <span class="inline-flex items-center gap-1 text-[11px] text-teal font-medium mt-0.5">
                                                <x-icon name="check" class="h-3 w-3" /> Official Sponsor
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-[11px] text-muted mt-0.5">
                                                &bull; Custom Sponsor Name
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-5 py-4 max-w-xs">
                                        <div class="font-medium text-ink line-clamp-1">
                                            {{ $sub->title }}
                                        </div>
                                        <div class="text-xs text-muted line-clamp-1">
                                            {{ $sub->description }}
                                        </div>
                                        <div class="mt-1 flex flex-wrap gap-1.5">
                                            @if ($sub->highlight)
                                                <x-badge variant="gold">{{ $sub->highlight }}</x-badge>
                                            @endif
                                            @if ($sub->coupon_code)
                                                <code class="rounded bg-paper px-1.5 py-0.5 text-[11px] font-mono text-ink border border-line">{{ $sub->coupon_code }}</code>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 text-xs">
                                        <div class="font-medium text-ink">{{ $sub->submitter_name }}</div>
                                        <div class="text-muted text-[11px]">{{ $sub->submitter_email }}</div>

                                        <div class="mt-1">
                                            @if ($sub->emailDomainMatches() === true)
                                                <span class="inline-flex items-center gap-1 rounded bg-teal/10 px-2 py-0.5 text-[11px] font-medium text-teal border border-teal/20">
                                                    <x-icon name="check" class="h-3 w-3" /> Domain Match
                                                </span>
                                            @elseif ($sub->emailDomainMatches() === false)
                                                <span class="inline-flex items-center gap-1 rounded bg-gold/15 px-2 py-0.5 text-[11px] font-medium text-gold-dark border border-gold/30">
                                                    Non-domain / generic email
                                                </span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-5 py-4 whitespace-nowrap">
                                        @if ($sub->isPending())
                                            <x-badge variant="gold">Pending Review</x-badge>
                                        @elseif ($sub->isApproved())
                                            <x-badge variant="success">Approved</x-badge>
                                        @else
                                            <x-badge variant="muted">{{ ucfirst($sub->status) }}</x-badge>
                                        @endif
                                    </td>

                                    <td class="px-5 py-4 whitespace-nowrap text-right text-xs">
                                        <div class="flex items-center justify-end gap-2">
                                            <x-button :href="route('admin.deal-submissions.show', $sub)" size="sm" variant="secondary" icon="eye">
                                                Review
                                            </x-button>

                                            @if ($sub->isPending())
                                                {{-- Quick Approve as Draft --}}
                                                <form method="POST" action="{{ route('admin.deal-submissions.approve', $sub) }}" class="inline">
                                                    @csrf
                                                    <input type="hidden" name="publish" value="0" />
                                                    <button type="submit"
                                                            class="inline-flex items-center gap-1 rounded-md bg-teal px-2.5 py-1 text-xs font-semibold text-white shadow-2xs hover:bg-teal-dark focus:outline-hidden focus-visible:ring-2 focus-visible:ring-teal/60">
                                                        <x-icon name="check" class="h-3.5 w-3.5" />
                                                        Draft
                                                    </button>
                                                </form>
                                            @endif

                                            <x-action-form :action="route('admin.deal-submissions.destroy', $sub)" method="DELETE"
                                                           variant="danger-outline" size="sm" icon="trash"
                                                           :confirm="'Delete deal submission for “'.$sub->displayBrand().'”? This cannot be undone.'">
                                                Delete
                                            </x-action-form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($submissions->hasPages())
                    <div class="p-4 border-t border-line">
                        {{ $submissions->links() }}
                    </div>
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
