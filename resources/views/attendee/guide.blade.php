<x-attendee-layout :event="$event" title="Guide">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1" class="guide">
        @include('attendee.partials.guide-body', ['event' => $event])
    </main>

    {{-- Just enough of the schedule for guide.js to put real times next to
    "Registration", "Lunch", "Keynote"… — titles, starts, tracks, and what's needed
    to tell a talk from a break (moments.js). --}}
    <script type="application/json" id="guide-data">{!! json_encode(
        collect($sessions)->filter(fn ($s) => is_array($s) && ! empty($s['starts_at']))->map(fn ($s) => [
            'title' => $s['title'] ?? '',
            'starts_at' => $s['starts_at'],
            'track' => $s['track_names'][0] ?? null,
            'session_type' => $s['session_type'] ?? null,
            'speaker_ids' => $s['speaker_ids'] ?? [],
        ])->values(),
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    ) !!}</script>
</x-attendee-layout>
