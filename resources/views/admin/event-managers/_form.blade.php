{{-- The event manager fields, shared by create and edit. $manager, $events (every event, soonest first), $assigned (ids). --}}
@php
    $selected = array_map('strval', (array) old('events', $assigned));
    $creating = ! $manager->exists;
@endphp

<div class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
    <x-form.input name="name" label="Name" required :autofocus="$creating" :value="$manager->name" autocomplete="off" />

    <x-form.input name="email" type="email" label="Email" required :value="$manager->email" autocomplete="off"
                  hint="They sign in with this." />

    <x-form.input name="phone" type="tel" label="Phone number" required :value="$manager->phone" autocomplete="off"
                  placeholder="+91 98765 43210" />

    <x-form.password name="password" :label="$creating ? 'Password' : 'New password'" :required="$creating" autocomplete="new-password"
                     :hint="$creating
                        ? 'At least 8 characters. You hand it to them — they can\'t change it themselves.'
                        : 'Leave blank to keep the current password. A new one signs them out everywhere.'" />

    <div class="sm:col-span-2">
        <x-form.checkbox name="is_active" label="Active"
                         hint="Untick to stop this person signing in, right away. Nothing is deleted."
                         :checked="$creating ? true : $manager->is_active" unchecked="0" />
    </div>

    {{-- The WordCamps this person may edit. Filtering only hides rows; hidden ticked rows still submit. --}}
    <fieldset class="sm:col-span-2" x-data="{ q: '', n: {{ count($selected) }} }"
              x-on:change="n = $root.querySelectorAll('input[name=&quot;events[]&quot;]:checked').length">
        <legend class="cb-label mb-1">
            WordCamps they can edit<span class="ms-0.5 text-danger" aria-hidden="true">*</span>
            <span class="ms-1 font-normal text-muted">(<span x-text="n">{{ count($selected) }}</span> selected)</span>
        </legend>

        <label class="sr-only" for="event-filter">Filter the WordCamps</label>
        <input id="event-filter" type="search" x-model="q" placeholder="Filter the list…" autocomplete="off" class="cb-input mb-2">

        <div class="max-h-80 divide-y divide-line overflow-y-auto rounded-lg border border-line bg-white">
            @forelse ($events as $event)
                <label class="flex cursor-pointer items-start gap-3 px-3 py-2.5 hover:bg-paper-soft"
                       data-name="{{ \Illuminate\Support\Str::lower($event->display_name.' '.$event->slug) }}"
                       x-show="$el.dataset.name.includes(q.trim().toLowerCase())">
                    <input type="checkbox" name="events[]" value="{{ $event->id }}" @checked(in_array((string) $event->id, $selected, true)) class="cb-check mt-0.5">
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium">{{ $event->display_name }}</span>
                        <span class="block text-xs text-muted">
                            @if ($event->starts_on)
                                {{ $event->starts_on->format('j M Y') }}@if ($event->ends_on && ! $event->ends_on->isSameDay($event->starts_on)) – {{ $event->ends_on->format('j M Y') }}@endif
                            @else
                                No date yet
                            @endif
                        </span>
                    </span>
                    <x-event-status :status="$event->status" />
                </label>
            @empty
                <p class="px-3 py-6 text-center text-sm text-muted">There are no events yet — add one first.</p>
            @endforelse
        </div>

        @if ($errors->has('events') || $errors->has('events.*'))
            <p class="cb-error" role="alert">{{ $errors->first('events') ?: $errors->first('events.*') }}</p>
        @else
            <p class="cb-hint">They can edit the event's details, event information and quests &amp; checklist — never its status.</p>
        @endif
    </fieldset>
</div>
