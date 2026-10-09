{{--
    One attendee's roles, ticked by hand (App\Support\RosterMarks) — on the
    admin Roster page and the event manager's Attendees page. "auto" marks a
    role CampBuddy found by itself (speaker/organizer pages, volunteers,
    microsponsor block); unticking it takes it off for this person.

      @include('partials.roster-roles-form', ['action' => $url, 'entry' => $entry, 'current' => [...], 'auto' => [...]])
--}}
{{-- Opens in place (not a floating box): the table around it clips anything that sticks out. --}}
<details class="group text-left">
    <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded-md border border-line px-2 py-1 text-xs font-medium text-ink hover:bg-paper-soft focus:outline-none focus-visible:ring-2 focus-visible:ring-maroon/40">
        <x-icon name="pencil" class="h-3.5 w-3.5" /> Roles
    </summary>
    <form method="POST" action="{{ $action }}" class="mt-2 w-56 space-y-2 rounded-lg border border-line bg-white p-3 text-left shadow-sm">
        @csrf
        <p class="text-xs font-semibold text-muted">Roles of {{ $entry->name }}</p>
        @foreach (\App\Support\RosterRoles::LABELS as $role => $label)
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="roles[]" value="{{ $role }}" class="cb-check" @checked(in_array($role, $current, true))>
                <span>{{ $label }}</span>
                @if (in_array($role, $auto, true))
                    <span class="text-[11px] text-muted">auto</span>
                @endif
            </label>
        @endforeach
        <x-button size="sm" class="w-full justify-center">Save roles</x-button>
    </form>
</details>
