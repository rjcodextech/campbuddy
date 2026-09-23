<x-attendee-layout :event="$event">
    <header class="topbar">
        <span class="brand">
            @if ($event->logoUrl())
                <img src="{{ $event->logoUrl() }}" alt="" class="brand__logo">
            @endif
            <span>{{ $event->display_name }}</span>
        </span>
    </header>

    <main id="main-content" tabindex="-1">
        <div class="section-head">
            <h1 class="section-head__title">Remove me from the attendee list</h1>
        </div>

        <p class="footer-note" style="text-align:left">
            {{ $event->display_name }}'s public Attendees page is opted into by you at checkout, and CampBuddy mirrors
            it here. If you'd rather not appear, search your name below and remove yourself — no login needed.
        </p>

        @if (session('status'))
            <div class="notice" style="margin-top:14px">{{ session('status') }}</div>
        @endif

        <form method="GET" action="{{ route('event.roster-removal.search', $event) }}" class="card" style="margin-top:16px">
            <label class="field">
                <span>Your name, exactly as it appears on the Attendees page</span>
                <input type="text" name="name" value="{{ $searchedName ?? '' }}" required>
            </label>
            <button type="submit" class="btn btn--primary btn--full">Find my listing</button>
        </form>

        @isset($matches)
            <div style="margin-top:16px">
                @if ($matches->isEmpty())
                    <p class="footer-note" style="text-align:left">
                        No public listing found for "{{ $searchedName }}" — it may already be removed, or the name
                        may not match exactly.
                    </p>
                @else
                    @foreach ($matches as $entry)
                        <div class="card" style="display:flex;align-items:center;gap:12px;justify-content:space-between">
                            <div style="display:flex;align-items:center;gap:10px">
                                @if ($entry->gravatar_url)
                                    <img src="{{ $entry->gravatar_url }}" alt="" style="width:40px;height:40px;border-radius:50%">
                                @endif
                                <span style="font-weight:700">{{ $entry->name }}</span>
                            </div>
                            <form method="POST" action="{{ route('event.roster-removal.remove', [$event, $entry]) }}"
                                  onsubmit="return confirm('Remove this listing? This can\'t be undone by you — contact the organizers if you change your mind.');">
                                @csrf
                                <button type="submit" class="btn btn--danger btn--compact">This is me — remove</button>
                            </form>
                        </div>
                    @endforeach
                @endif
            </div>
        @endisset
    </main>
</x-attendee-layout>
