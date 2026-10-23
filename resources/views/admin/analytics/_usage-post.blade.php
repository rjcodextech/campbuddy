{{--
    "CampBuddy at {WordCamp}": a ready social media post (up to 5 images and a
    caption) with how a WordCamp used CampBuddy, from 3 days before it to 3
    days after (App\Support\UsagePost; drawn by resources/js/usage-post.js).
    Once made it opens as a small studio: the cards on the left, the chosen one
    large in the middle, and everything that changes it on the right — the
    template (Classic, Daylight, Bold), colours, each card's title, which cards
    go in, the size — then one ZIP with the images and the three captions.
--}}
<x-card title="Usage post" description="A social media post about how a WordCamp used CampBuddy: up to five images and a caption each for LinkedIn, Instagram and X, with real numbers from 3 days before the WordCamp to 3 days after it. Small numbers are left out." class="mb-6" data-usage-post
        data-url="{{ route('admin.analytics.usage-post') }}" data-stream="{{ $filters['stream'] }}">
    <div class="flex flex-wrap items-end gap-3">
        <label class="flex min-w-0 max-w-full flex-col gap-1 text-sm">
            <span class="cb-label">WordCamp</span>
            <select class="cb-input w-auto max-w-full" data-usage-event>
                @forelse ($usageEvents as $slug => $name)
                    <option value="{{ $slug }}">{{ $name }}</option>
                @empty
                    <option value="" disabled selected>No WordCamp has started yet</option>
                @endforelse
            </select>
        </label>
        <x-button type="button" icon="refresh" data-usage-make :disabled="$usageEvents === []">Make the post</x-button>
        <label class="flex items-center gap-2 text-sm text-muted"><input type="checkbox" class="cb-check" data-usage-fresh> Fresh numbers (skip the hour's cache)</label>
    </div>
    <p class="mt-3 text-sm text-muted" data-usage-status aria-live="polite"></p>

    <div class="mt-4 hidden" data-usage-result>
        {{-- The studio: cards | preview | edits. --}}
        <div class="grid gap-4 overflow-hidden rounded-xl border border-line bg-white lg:grid-cols-[11rem_minmax(0,1fr)_20rem]">
            <ol class="flex gap-2 overflow-auto border-b border-line p-2 lg:max-h-152 lg:flex-col lg:border-b-0 lg:border-r" data-usage-rail aria-label="Cards"></ol>
            <div class="flex min-h-96 items-center justify-center bg-paper-soft p-4">
                <canvas class="block max-h-144 w-auto max-w-full rounded-xl border border-line shadow-lg" data-usage-stage aria-label="The chosen card"></canvas>
            </div>
            <div class="space-y-4 border-t border-line p-4 lg:max-h-152 lg:overflow-y-auto lg:border-l lg:border-t-0">
                <div>
                    <span class="cb-label" id="usage-template-label">Template</span>
                    <div class="mt-1 flex flex-wrap gap-1" role="group" aria-labelledby="usage-template-label">
                        @foreach (['classic' => 'Classic', 'daylight' => 'Daylight', 'bold' => 'Bold'] as $key => $label)
                            <button type="button" class="cb-btn cb-btn-sm {{ $loop->first ? 'cb-btn-primary' : 'cb-btn-secondary' }}" data-usage-template="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
                <label class="block">
                    <span class="cb-label">This card's title</span>
                    <input type="text" maxlength="60" class="cb-input mt-1" data-usage-title>
                </label>
                <label class="block">
                    <span class="cb-label">Cover headline</span>
                    <input type="text" maxlength="80" class="cb-input mt-1" data-usage-headline>
                </label>
                <div>
                    <span class="cb-label">Colours</span>
                    <div class="mt-1 flex flex-wrap items-end gap-3">
                        @foreach (['primary' => 'Main', 'secondary' => 'Accent', 'ink' => 'Text', 'paper' => 'Light'] as $key => $label)
                            <label class="flex flex-col gap-1 text-xs text-muted">{{ $label }}<input type="color" class="h-9 w-12 cursor-pointer rounded border border-line" data-usage-color="{{ $key }}"></label>
                        @endforeach
                        <x-button type="button" variant="secondary" size="sm" data-usage-colors-reset>Reset</x-button>
                    </div>
                </div>
                <div>
                    <span class="cb-label">Cards in the post</span>
                    <div class="mt-1 space-y-1 text-sm" data-usage-cards></div>
                </div>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" class="cb-check" data-usage-range checked> Show the date range on the cover</label>
                <div class="border-t border-line pt-3">
                    <x-button type="button" icon="download" class="w-full" data-usage-download>Download images + captions (ZIP)</x-button>
                </div>
            </div>
        </div>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            @foreach (['linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'x' => 'X'] as $key => $network)
                <div class="flex min-w-0 flex-col gap-1 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <label class="cb-label" for="usage-caption-{{ $key }}">{{ $network }}</label>
                        @if ($key === 'x')
                            <span class="text-xs tabular-nums text-muted" data-usage-x-count></span>
                        @endif
                        <x-button type="button" variant="secondary" size="sm" data-usage-copy="{{ $key }}">Copy</x-button>
                    </div>
                    <textarea id="usage-caption-{{ $key }}" class="cb-input font-normal" rows="10" data-usage-caption="{{ $key }}"></textarea>
                </div>
            @endforeach
        </div>
    </div>
</x-card>
