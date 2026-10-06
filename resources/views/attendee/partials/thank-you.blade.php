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

        <div class="thank-you__actions">
            <button type="button" class="btn btn--outline btn--full" data-thank-you-pdf>
                <x-attendee.line-icon name="download" /> Save my day as PDF
            </button>
            <p class="thank-you__hint">Your sessions, people and quests live only on this phone, and this WordCamp closes in CampBuddy {{ $daysSince !== null ? 'in '.max(1, \App\Support\EventTime::RETENTION_DAYS - $daysSince + 1).' '.\Illuminate\Support\Str::plural('day', max(1, \App\Support\EventTime::RETENTION_DAYS - $daysSince + 1)) : 'soon' }}.</p>
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
