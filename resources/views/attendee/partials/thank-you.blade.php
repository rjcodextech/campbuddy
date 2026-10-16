{{--
    After a WordCamp: the thank-you card. thank-you.js opens it (as a modal)
    from EventTime::THANK_YOU_AFTER_DAYS after the last day until the event
    is archived, once per phone, and only on a phone that holds something for
    this event — or straight away from the day-3 push (?thanks=1).
    A rating (stars + optional comment), "Save my day as PDF", and a nudge
    towards the next WordCamp in the same country.

    Included by layouts/attendee.blade.php (event pages, once it's over) and
    welcome.blade.php (one per "Completed" card).
--}}
@php
    $daysSince = \App\Support\EventTime::daysSinceEnd($event);
    $countryCode = \App\Support\EventCountry::code($event);
    $countryName = $countryCode ? \App\Support\EventCountry::name($countryCode) : null;
    $upcomingNearby = $countryCode
        ? \App\Models\Event::where('status', 'active')->where('is_visible', true)->where('country_code', $countryCode)
            ->where('id', '!=', $event->id)->where('starts_on', '>=', today()->toDateString())->count()
        : 0;
    $questTitles = \App\Models\Quest::where(fn ($q) => $q->whereNull('event_id')->orWhere('event_id', $event->id))->pluck('title', 'id');
    $dialogId = 'thank-you-'.$event->id;
@endphp

<dialog id="{{ $dialogId }}" class="thank-you" aria-labelledby="{{ $dialogId }}-title"
        data-thank-you
        data-event-id="{{ $event->id }}"
        data-event-slug="{{ $event->slug }}"
        data-event-name="{{ $event->display_name }}"
        data-days-since="{{ $daysSince ?? '' }}"
        data-from-day="{{ \App\Support\EventTime::THANK_YOU_AFTER_DAYS }}">
    <div class="thank-you__art">
        <x-attendee.line-icon name="party" class="thank-you__art-icon" />
        <p class="u-eyebrow thank-you__eyebrow">That's a wrap</p>
        <h2 id="{{ $dialogId }}-title" class="thank-you__title">Thank you for being at {{ $event->display_name }}</h2>
        <p class="thank-you__lede">The talks, the hallway chats, the people you met: you made it what it was.</p>
    </div>

    <div class="thank-you__body">
        <form class="thank-you__rate" data-thank-you-form>
            <fieldset class="thank-you__stars">
                <legend class="thank-you__question">How was CampBuddy at this WordCamp?</legend>
                @for ($i = 1; $i <= 5; $i++)
                    <label class="thank-you__star">
                        <input type="radio" name="rating" value="{{ $i }}" class="u-visually-hidden">
                        <x-attendee.line-icon name="star" />
                        <span class="u-visually-hidden">{{ $i }} out of 5</span>
                    </label>
                @endfor
            </fieldset>

            <div class="thank-you__more" data-thank-you-more hidden>
                <label for="{{ $dialogId }}-comment" class="thank-you__label">Anything we should keep, fix or add? <span class="thank-you__optional">(optional)</span></label>
                <textarea id="{{ $dialogId }}-comment" name="comment" rows="3" maxlength="500" class="thank-you__comment"></textarea>
                {{-- Honeypot: people never see it, bots fill it. --}}
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="u-visually-hidden" aria-hidden="true">
                <button type="submit" class="btn btn--primary btn--full">Send feedback</button>
            </div>

            <p class="thank-you__sent" data-thank-you-sent hidden><x-attendee.line-icon name="heart" /> Thank you! That helps the next WordCamp.</p>
            <p class="thank-you__error" data-thank-you-error role="alert" hidden></p>
        </form>

        @php($memoryLinks = \App\Support\EventMemories::links($event))
        @if ($memoryLinks !== [])
            {{-- Memories (EventMemories): the photo album and the talks, once the organizers add them. --}}
            <div class="thank-you__memories">
                <p class="thank-you__question">Relive it</p>
                @isset($memoryLinks['photos'])
                    <a href="{{ $memoryLinks['photos'] }}" target="_blank" rel="noopener" class="btn btn--outline btn--full" data-track="memories_open" data-track-target="photos"><x-attendee.line-icon name="camera" /> See the photos</a>
                @endisset
                @isset($memoryLinks['videos'])
                    <a href="{{ $memoryLinks['videos'] }}" target="_blank" rel="noopener" class="btn btn--outline btn--full" data-track="memories_open" data-track-target="videos"><x-attendee.line-icon name="play" /> Watch the talks on WordPress.tv</a>
                @endisset
            </div>
        @endif

        <div class="thank-you__retention" data-thank-you-retention>
            <p class="thank-you__question">
                <x-attendee.line-icon name="shield-check" /> Event data &amp; retention
            </p>
            <p class="thank-you__hint">
                Your saved sessions, notes and quests are stored on this device. Under DPDPA 2023, what should happen to this event's data?
            </p>

            <div class="thank-you__retention-options">
                <label class="thank-you__retention-option">
                    <input type="radio" name="retention_choice_{{ $event->id }}" value="keep" class="thank-you__retention-radio">
                    <div class="thank-you__retention-info">
                        <strong>Keep my data in app</strong>
                        <span>Stays on this device until you clear it yourself.</span>
                    </div>
                </label>

                <label class="thank-you__retention-option">
                    <input type="radio" name="retention_choice_{{ $event->id }}" value="auto_delete" class="thank-you__retention-radio">
                    <div class="thank-you__retention-info">
                        <strong>Delete after {{ \App\Support\EventTime::RETENTION_DAYS }} days</strong>
                        <span>Automatically deleted from this device once the retention period ends.</span>
                    </div>
                </label>

                <label class="thank-you__retention-option">
                    <input type="radio" name="retention_choice_{{ $event->id }}" value="delete_now" class="thank-you__retention-radio">
                    <div class="thank-you__retention-info">
                        <strong>Delete my data now</strong>
                        <span>Instantly removes this event's saved data from this device and leaves discovery.</span>
                    </div>
                </label>
            </div>

            <div class="thank-you__retention-actions">
                <button type="button" class="btn btn--primary btn--full" data-retention-confirm disabled>Confirm preference</button>
                <p class="thank-you__retention-status" data-retention-status hidden></p>
            </div>
        </div>

        <div class="thank-you__actions">
            <button type="button" class="btn btn--outline btn--full" data-thank-you-pdf>
                <x-attendee.line-icon name="download" /> Save my day as PDF
            </button>
            {{-- Days left of the retention window: the people met can still find this person (discovery, shared Camp Card). --}}
            @php($daysLeft = $daysSince !== null ? \App\Support\EventTime::RETENTION_DAYS - $daysSince + 1 : null)
            {{-- Shown by move-data.js only when this phone holds a profile or people met to bring. --}}
            <a href="{{ route('event.home', $event) }}?move=1" class="btn btn--outline btn--full thank-you__move" data-open-move hidden>
                <x-attendee.line-icon name="share" /> Next WordCamp? Bring your people along
            </a>
            <p class="thank-you__hint">Your sessions, people and quests live only on this phone: save them as a PDF to keep them.@if ($daysLeft !== null && $daysLeft > 0) The people you met can still find you here for {{ $daysLeft }} more {{ \Illuminate\Support\Str::plural('day', $daysLeft) }}.@endif</p>
        </div>

        <a href="{{ route('home') }}#find-your-camp" class="thank-you__next" data-thank-you-next>
            <x-attendee.line-icon name="compass" class="thank-you__next-icon" />
            <span>
                @if ($upcomingNearby > 0)
                    <strong>Your next WordCamp is already on the map.</strong>
                    {{ $upcomingNearby }} more coming up in {{ $countryName }}. Find yours
                @else
                    <strong>The next WordCamp is never far.</strong>
                    See what's coming up{{ $countryName ? ' near '.$countryName : '' }}
                @endif
                <span aria-hidden="true">→</span>
            </span>
        </a>
    </div>

    <button type="button" class="thank-you__close" data-thank-you-close aria-label="Close"><x-attendee.line-icon name="x" /></button>

    <script type="application/json" data-thank-you-quests>{!! json_encode((object) $questTitles->all(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
</dialog>
