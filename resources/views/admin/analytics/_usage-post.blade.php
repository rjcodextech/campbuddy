{{--
    "CampBuddy at {WordCamp}": a ready social media post (up to 5 images and a
    caption) with how a WordCamp used CampBuddy, from 3 days before it to 3
    days after (App\Support\UsagePost; drawn by resources/js/usage-post.js).
--}}
<x-card title="Usage post" description="A social media post about how a WordCamp used CampBuddy: up to five images (1080 × 1350) and a caption, with real numbers from 3 days before the WordCamp to 3 days after it. Small numbers are left out." class="mb-6" data-usage-post
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
        <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_auto]">
            <label class="flex flex-col gap-1 text-sm">
                <span class="cb-label">Caption</span>
                <textarea class="cb-input font-normal" rows="9" data-usage-caption></textarea>
            </label>
            <div class="flex flex-wrap items-start gap-2 lg:flex-col">
                <x-button type="button" icon="download" data-usage-download>Download images (ZIP)</x-button>
                <x-button type="button" variant="secondary" data-usage-copy>Copy caption</x-button>
            </div>
        </div>
    </div>
</x-card>
