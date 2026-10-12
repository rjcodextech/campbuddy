{{--
    "Take it with you" (move-data.js): after a WordCamp, bring the profile and
    the people met to the next WordCamp, or save it all as a PDF. Opens from
    the day-15 push (?move=1) and from the thank-you card, and only on a phone
    that holds a profile or people met for this event.

    Included by layouts/attendee.blade.php on an event's pages once it's over.
--}}
@php($moveId = 'move-data-'.$event->id)
<dialog id="{{ $moveId }}" class="thank-you move-data" aria-labelledby="{{ $moveId }}-title" data-move-data data-event-id="{{ $event->id }}">
    <div class="thank-you__art">
        <x-attendee.line-icon name="share" class="thank-you__art-icon" />
        <p class="u-eyebrow thank-you__eyebrow">Take it with you</p>
        <h2 id="{{ $moveId }}-title" class="thank-you__title">Bring it to your next WordCamp</h2>
        <p class="thank-you__lede">Your profile and the people you met at {{ $event->display_name }} live only on this phone. Bring them along to the next WordCamp you open in CampBuddy.</p>
    </div>

    <div class="thank-you__body move-data__body">
        <a href="{{ route('home') }}#find-your-camp" class="move-data__option" data-move-action="next_wordcamp">
            <span class="move-data__icon" aria-hidden="true"><x-attendee.line-icon name="compass" /></span>
            <span>
                <strong class="move-data__title">Going to another WordCamp?</strong>
                <span class="move-data__text">Open it in CampBuddy on this phone. We'll offer to bring your profile and the people you met, so meeting people there starts in one tap.</span>
                <span class="move-data__cta">Find your next WordCamp <span aria-hidden="true">→</span></span>
            </span>
        </a>

        <button type="button" class="move-data__option move-data__option--button" data-move-pdf>
            <span class="move-data__icon" aria-hidden="true"><x-attendee.line-icon name="download" /></span>
            <span>
                <strong class="move-data__title">Keep a copy</strong>
                <span class="move-data__text">Save your day as a PDF: your sessions, the people you met with their links, your notes and quests.</span>
                <span class="move-data__cta">Save my day as PDF</span>
            </span>
        </button>
    </div>

    <button type="button" class="thank-you__close" data-move-close aria-label="Close"><x-attendee.line-icon name="x" /></button>
</dialog>
