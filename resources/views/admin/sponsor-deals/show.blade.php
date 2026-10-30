<x-app-layout :title="'Review Deal: ' . $submission->displayBrand()"
              :subtitle="$submission->event?->display_name"
              :breadcrumbs="[['Deals & offers'], ['Deal submissions', route('admin.deal-submissions.index')], [$submission->displayBrand()]]">

    <x-slot:actions>
        <x-button :href="route('admin.deal-submissions.index')" variant="secondary" icon="arrow-left">Back to Submissions</x-button>
    </x-slot:actions>

    <div class="max-w-6xl space-y-6">
        @if ($submission->isApproved())
            <x-alert type="success">
                This submission was approved! The deal is stored in the <strong>{{ $submission->event?->display_name }}</strong> deals table.
                @if ($submission->approvedOffer)
                    <a href="{{ route('admin.events.offers.edit', [$submission->event, $submission->approvedOffer]) }}" class="font-medium underline ml-1">
                        Edit in event offers &rarr;
                    </a>
                @endif
            </x-alert>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            {{-- Left column: Verification details & Actions --}}
            <div class="lg:col-span-7 space-y-6">
                {{-- Actions Card --}}
                <x-card title="Review Decision" description="Verify details below before taking action.">
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($submission->isPending())
                            {{-- Approve as Draft --}}
                            <form method="POST" action="{{ route('admin.deal-submissions.approve', $submission) }}">
                                @csrf
                                <input type="hidden" name="publish" value="0" />
                                <x-button type="submit" variant="secondary" icon="document-text">
                                    Approve as Draft Deal
                                </x-button>
                            </form>

                            {{-- Approve and Publish --}}
                            <form method="POST" action="{{ route('admin.deal-submissions.approve', $submission) }}">
                                @csrf
                                <input type="hidden" name="publish" value="1" />
                                <x-button type="submit" variant="primary" icon="check">
                                    Approve & Publish Immediately
                                </x-button>
                            </form>
                        @endif

                        {{-- Delete --}}
                        <x-action-form :action="route('admin.deal-submissions.destroy', $submission)" method="DELETE"
                                       variant="danger-outline" icon="trash"
                                       :confirm="'Are you sure you want to permanently delete this submission for “'.$submission->displayBrand().'”? This will purge the temporary record.'">
                            Reject & Delete
                        </x-action-form>
                    </div>
                </x-card>

                {{-- Submitter Verification Card --}}
                <x-card title="Submitter & Authenticity Signals">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <span class="block text-xs font-semibold uppercase tracking-wider text-muted">Submitted By</span>
                                <span class="text-sm font-semibold text-ink">{{ $submission->submitter_name }}</span>
                            </div>

                            <div>
                                <span class="block text-xs font-semibold uppercase tracking-wider text-muted">Work Email</span>
                                <a href="mailto:{{ $submission->submitter_email }}" class="text-sm font-medium text-maroon-text hover:underline">
                                    {{ $submission->submitter_email }}
                                </a>
                            </div>
                        </div>

                        {{-- Security Signals --}}
                        <div class="rounded-lg bg-paper-soft border border-line p-4 space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-muted">Domain Authenticity Check:</span>
                                @if ($submission->emailDomainMatches() === true)
                                    <span class="inline-flex items-center gap-1 rounded bg-teal/15 px-2.5 py-0.5 text-xs font-semibold text-teal border border-teal/20">
                                        <x-icon name="check" class="h-3.5 w-3.5" /> Work email domain matches sponsor domain
                                    </span>
                                @elseif ($submission->emailDomainMatches() === false)
                                    <span class="inline-flex items-center gap-1 rounded bg-gold/15 px-2.5 py-0.5 text-xs font-semibold text-gold-dark border border-gold/30">
                                        Non-matching or generic email domain
                                    </span>
                                @else
                                    <span class="text-xs text-muted">No website specified to verify against</span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between">
                                <span class="text-xs font-medium text-muted">Official WordCamp Sponsor List:</span>
                                @if ($submission->isOfficialSponsor())
                                    <span class="inline-flex items-center gap-1 rounded bg-teal/15 px-2.5 py-0.5 text-xs font-semibold text-teal border border-teal/20">
                                        <x-icon name="check" class="h-3.5 w-3.5" /> Matched Ingested Event Sponsor
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded bg-paper px-2.5 py-0.5 text-xs font-semibold text-muted border border-line">
                                        Custom / Unlisted Sponsor Name
                                    </span>
                                @endif
                            </div>

                            <div class="flex items-center justify-between text-xs text-muted">
                                <span>IP Address & Submission Time:</span>
                                <span>{{ $submission->ip_address ?? '—' }} &bull; {{ $submission->created_at->format('j M Y, g:i A') }}</span>
                            </div>
                        </div>

                        @if ($submission->submitter_phone)
                            <div>
                                <span class="block text-xs font-semibold uppercase tracking-wider text-muted">Contact Phone / WhatsApp</span>
                                <span class="text-sm font-medium text-ink">{{ $submission->submitter_phone }}</span>
                            </div>
                        @endif

                        @if ($submission->notes)
                            <div>
                                <span class="block text-xs font-semibold uppercase tracking-wider text-muted">Notes from Submitter</span>
                                <p class="mt-1 text-sm text-ink bg-paper-soft rounded-lg p-3 border border-line">
                                    {{ $submission->notes }}
                                </p>
                            </div>
                        @endif
                    </div>
                </x-card>

                {{-- Submitted Form Details --}}
                <x-card title="Deal Parameters">
                    <dl class="divide-y divide-line text-sm">
                        <div class="py-2.5 flex justify-between gap-4">
                            <dt class="text-muted">Target WordCamp</dt>
                            <dd class="font-medium text-ink text-right">{{ $submission->event?->display_name }}</dd>
                        </div>
                        <div class="py-2.5 flex justify-between gap-4">
                            <dt class="text-muted">Sponsor Name</dt>
                            <dd class="font-medium text-ink text-right">{{ $submission->sponsor_name }}</dd>
                        </div>
                        <div class="py-2.5 flex justify-between gap-4">
                            <dt class="text-muted">Brand Title</dt>
                            <dd class="font-medium text-ink text-right">{{ $submission->brand ?: '—' }}</dd>
                        </div>
                        <div class="py-2.5 flex justify-between gap-4">
                            <dt class="text-muted">Sponsor Website</dt>
                            <dd class="font-medium text-ink text-right">{{ $submission->website ?: '—' }}</dd>
                        </div>
                        <div class="py-2.5 flex justify-between gap-4">
                            <dt class="text-muted">Headline Title</dt>
                            <dd class="font-semibold text-ink text-right">{{ $submission->title }}</dd>
                        </div>
                        <div class="py-2.5 flex justify-between gap-4">
                            <dt class="text-muted">Highlight Tag</dt>
                            <dd class="font-medium text-ink text-right">
                                @if ($submission->highlight)
                                    <x-badge variant="gold">{{ $submission->highlight }}</x-badge>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="py-2.5 flex justify-between gap-4">
                            <dt class="text-muted">Coupon Code</dt>
                            <dd class="font-medium text-ink text-right">
                                @if ($submission->coupon_code)
                                    <code class="rounded bg-paper px-2 py-0.5 font-mono text-xs">{{ $submission->coupon_code }}</code>
                                @else
                                    —
                                @endif
                            </dd>
                        </div>
                        <div class="py-2.5 flex justify-between gap-4">
                            <dt class="text-muted">Deal URL</dt>
                            <dd class="font-medium text-ink text-right truncate max-w-xs">
                                <a href="{{ $submission->url }}" target="_blank" class="text-maroon-text underline">
                                    {{ $submission->url }}
                                </a>
                            </dd>
                        </div>
                        <div class="py-2.5 flex justify-between gap-4">
                            <dt class="text-muted">Button Label</dt>
                            <dd class="font-medium text-ink text-right">{{ $submission->cta_label ?: 'Get the deal' }}</dd>
                        </div>
                        <div class="py-2.5 flex justify-between gap-4">
                            <dt class="text-muted">Emoji Icon</dt>
                            <dd class="font-medium text-ink text-right text-lg">{{ $submission->icon ?: '🏷' }}</dd>
                        </div>
                        @if ($submission->terms)
                            <div class="py-2.5">
                                <dt class="text-muted mb-1">Terms & Conditions</dt>
                                <dd class="text-xs text-muted italic">{{ $submission->terms }}</dd>
                            </div>
                        @endif
                        <div class="py-2.5">
                            <dt class="text-muted mb-1">Deal Description</dt>
                            <dd class="text-xs text-ink whitespace-pre-line leading-relaxed">{{ $submission->description }}</dd>
                        </div>
                    </dl>
                </x-card>
            </div>

            {{-- Right column: Live Deal Card Preview --}}
            <div class="lg:col-span-5 sticky top-8 space-y-4">
                <div class="rounded-xl border border-line bg-white p-6 shadow-xs">
                    <div class="flex items-center justify-between border-b border-line pb-3 mb-4">
                        <h3 class="text-sm font-semibold text-ink flex items-center gap-2">
                            <x-icon name="device-phone" class="h-4 w-4 text-muted" />
                            <span>Attendee Deal Card Preview</span>
                        </h3>
                        <span class="text-xs px-2 py-0.5 rounded-full bg-paper text-muted font-mono">Explore &rarr; Deals</span>
                    </div>

                    {{-- Exact Look and Feel of CampBuddy Ticket --}}
                    <div class="rounded-xl border border-line/80 bg-paper-soft p-5 shadow-xs overflow-hidden">
                        {{-- Head --}}
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white border border-line shadow-2xs overflow-hidden">
                                    @if ($submission->logo_url)
                                        <img src="{{ $submission->logo_url }}" alt="" class="h-full w-full object-contain p-1" />
                                    @else
                                        <span class="text-xl">{{ $submission->icon ?: '🏷' }}</span>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <span class="block truncate text-sm font-bold text-ink">{{ $submission->displayBrand() }}</span>
                                    <span class="block truncate text-xs text-muted">{{ $submission->website ?: 'sponsor.com' }}</span>
                                </div>
                            </div>

                            @if ($submission->highlight)
                                <div>
                                    <span class="inline-block rounded-md bg-gold/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wider text-ink border border-gold/40">
                                        {{ $submission->highlight }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        {{-- Title --}}
                        <h4 class="mt-4 text-base font-bold text-ink leading-snug">{{ $submission->title }}</h4>

                        {{-- Perforation line --}}
                        <div class="my-3.5 border-t border-dashed border-line"></div>

                        {{-- Description --}}
                        <p class="text-xs text-muted leading-relaxed whitespace-pre-line">{{ $submission->description }}</p>

                        {{-- Terms --}}
                        @if ($submission->terms)
                            <div class="mt-2 text-[11px] text-muted italic">
                                * {{ $submission->terms }}
                            </div>
                        @endif

                        {{-- Coupon Code --}}
                        @if ($submission->coupon_code)
                            <div class="mt-4 flex items-center justify-between gap-2 rounded-lg bg-white border border-line px-3 py-2">
                                <div class="min-w-0">
                                    <span class="block text-[10px] uppercase font-semibold text-muted">Promo Code</span>
                                    <code class="text-xs font-mono font-bold text-maroon-text">{{ $submission->coupon_code }}</code>
                                </div>
                                <span class="text-xs font-medium px-2 py-1 rounded bg-paper text-muted border border-line">Copy</span>
                            </div>
                        @endif

                        {{-- CTA Button --}}
                        <div class="mt-4">
                            <a href="{{ $submission->url }}" target="_blank" rel="noopener"
                               class="flex items-center justify-center gap-1.5 w-full rounded-xl bg-navy py-2.5 px-4 text-xs font-semibold text-white shadow-xs hover:bg-navy-light transition-colors">
                                <span>{{ $submission->cta_label ?: 'Get the deal' }}</span>
                                <span>&rarr;</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
