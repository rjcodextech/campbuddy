{{-- An attendee's roles as small badges (admin Roster, manager Attendees). --}}
@foreach ($roles as $role)
    <x-badge variant="brand">{{ \App\Support\RosterRoles::LABELS[$role] ?? $role }}</x-badge>
@endforeach
