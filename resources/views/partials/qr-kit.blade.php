{{--
    The organizer QR kit (App\Support\QrKit) — the same cards on the admin's
    and an event manager's "QR codes" page. Each QR is drawn in the browser
    (resources/js/qr-kit.js) from its link; nothing is stored or sent.

      @include('partials.qr-kit', ['event' => $event, 'codes' => $codes])
--}}
<div class="max-w-5xl space-y-6">
    @unless ($event->status === 'active' && $event->is_visible)
        <x-alert type="warning">This event isn't live in the app yet. The codes already work: they open the event as soon as it is live.</x-alert>
    @endunless

    <p class="max-w-3xl text-sm text-muted">Each code opens this event in CampBuddy and tells Analytics where it was scanned, so you can see which one brings people. Use PNG for print (1200 × 1200 px; at least 2.5 cm wide on a badge) and SVG for designers.</p>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ($codes as $code)
            <x-card :title="$code['label']" :description="$code['hint']">
                <div class="flex flex-col items-center gap-4" data-qr-kit data-qr-url="{{ $code['url'] }}" data-qr-name="campbuddy-{{ $event->slug }}-{{ $code['key'] }}">
                    <div class="w-48 max-w-full rounded-lg border border-line bg-white p-2" data-qr-image role="img" aria-label="QR code: {{ $code['label'] }}"></div>

                    <div class="w-full">
                        <label class="sr-only" for="qr-link-{{ $code['key'] }}">Link</label>
                        <input id="qr-link-{{ $code['key'] }}" type="text" readonly value="{{ $code['url'] }}" class="cb-input w-full text-xs" data-qr-link>
                    </div>

                    <div class="flex flex-wrap justify-center gap-2">
                        <x-button type="button" size="sm" icon="download" data-qr-download="png">PNG</x-button>
                        <x-button type="button" variant="secondary" size="sm" icon="download" data-qr-download="svg">SVG</x-button>
                        <x-button type="button" variant="secondary" size="sm" data-qr-copy>Copy link</x-button>
                    </div>
                </div>
            </x-card>
        @endforeach
    </div>
</div>
