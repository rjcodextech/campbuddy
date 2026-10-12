{{--
    "CampBuddy at {WordCamp}": a ready social media post (up to 5 images and a
    caption) with how a WordCamp used CampBuddy, from 3 days before it to 3
    days after (App\Support\UsagePost; drawn by resources/js/usage-post.js).
--}}
<x-card title="Usage post" description="A social media post about how a WordCamp used CampBuddy: up to five images (4:5, 3240 × 4050 at 600 DPI) and a caption each for LinkedIn, Instagram and X, with real numbers from 3 days before the WordCamp to 3 days after it. Small numbers are left out." class="mb-6" data-usage-post
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
        <div class="flex gap-3 overflow-x-auto pb-2" data-usage-previews></div>
        <div class="mt-4">
            <x-button type="button" icon="download" data-usage-download>Download images + captions (ZIP)</x-button>
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
                    <textarea id="usage-caption-{{ $key }}" class="cb-input font-normal" rows="12" data-usage-caption="{{ $key }}"></textarea>
                </div>
            @endforeach
        </div>
    </div>
</x-card>
