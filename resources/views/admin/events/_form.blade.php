{{-- The core event fields, shared by create and edit. --}}
<div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
    <x-form.input name="display_name" label="Display name" required
                  :value="$event->display_name" placeholder="WordCamp Rajasthan 2026"
                  hint="Shown to attendees as the event's title." />

    <x-form.input name="slug" label="URL slug" required :autofocus="! $event->exists"
                  :value="$event->slug" placeholder="wordcamp-rajasthan-2026"
                  hint="Becomes the event's address: /event/your-slug" />

    <x-form.input name="short_name" label="Short name / hashtag"
                  :value="$event->short_name" placeholder="#WCRajasthan" />

    <x-form.input name="source_site_url" type="url" label="Source site URL" required
                  :value="$event->source_site_url" placeholder="https://rajasthan.wordcamp.org/2026"
                  hint="The event's own WordCamp.org site. Schedule and sponsors are read from here." />

    <x-form.input name="starts_on" type="date" label="Starts on"
                  :value="optional($event->starts_on)->toDateString()" />

    <x-form.input name="ends_on" type="date" label="Ends on"
                  :value="optional($event->ends_on)->toDateString()"
                  hint="Drives when attendee-discovery profiles expire." />

    {{-- Blank = read from the WordCamp site on every fetch; typed = kept. --}}
    <x-form.input name="timezone" label="Time zone" list="timezone-list"
                  :value="$event->timezone_locked ? $event->timezone : ''"
                  :placeholder="$event->timezone ? $event->timezone.' (from the WordCamp site)' : 'Read from the WordCamp site'"
                  hint="Session times, event days and when the discovery chat opens all use this. Leave blank to use the WordCamp site's own setting." />
    <datalist id="timezone-list">
        @foreach (\DateTimeZone::listIdentifiers() as $zoneName)
            <option value="{{ $zoneName }}"></option>
        @endforeach
    </datalist>

    <x-form.select name="status" label="Lifecycle status" required :value="$event->status ?? 'draft'"
                   :options="['draft' => 'Draft — not public', 'approved' => 'Approved — branding fetched', 'active' => 'Active — live in the app', 'archived' => 'Archived — read-only']"
                   hint="Only active, visible events appear to attendees." />

    <div class="sm:pt-7">
        <x-form.checkbox name="is_visible" label="Visible in the public app"
                         hint="Untick to hide an active event without archiving it."
                         :checked="$event->is_visible ?? true" unchecked="0" />
    </div>
</div>
