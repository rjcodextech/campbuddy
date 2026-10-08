{{--
    One Camp Card face — the markup camp-card-paint.js fills in. Used for each
    layout on the Camp Card page, and (Ticket, with the "made from public
    info" line) for a person's card on the attendee list.

      @include('attendee.partials.camp-card-face', ['key' => 'ticket', 'event' => $event, 'source' => true])
--}}
{{-- data-ask-bubble: Ticket shows "Ask me about" in its own speech bubble instead of as a tag (camp-card.js). --}}
<div class="camp-card camp-card--{{ $key }}" data-event-icon="{{ $event->faviconUrl() ?? '/media/icons/icon-192.png' }}" @if ($key === 'ticket') data-ask-bubble @endif>
    <span class="camp-card__lanyard-hole" aria-hidden="true"></span>
    {{-- Ticket only (hidden on the others): rings behind the event mark, and the tear line's two notches. --}}
    <span class="camp-card__rings" aria-hidden="true"><i></i><i></i><i></i></span>
    <span class="camp-card__notch camp-card__notch--left" aria-hidden="true"></span>
    <span class="camp-card__notch camp-card__notch--right" aria-hidden="true"></span>
    <div class="camp-card__body">
        <div class="camp-card__event">
            <img src="{{ $event->faviconUrl() ?? '/media/icons/icon-192.png' }}" alt="" class="camp-card__event-mark" data-fallback="/media/icons/icon-192.png">
            <span class="camp-card__event-name">{{ $event->display_name }}</span>
        </div>
        <img src="{{ $event->logoUrl() ?? '/media/logo-wordmark.png' }}" alt="{{ $event->display_name }}" class="camp-card__event-mark-full" data-fallback="/media/logo-wordmark.png">
        <p class="camp-card__hello">Hi, I'm</p>
        <p class="camp-card__name"></p>
        <p class="camp-card__role"></p>
        <div class="camp-card__tags"></div>
        <p class="camp-card__ask"></p>
    </div>
    <div class="camp-card__footer" hidden>
        <div class="camp-card__qr-frame">
            <canvas></canvas>
        </div>
        <p class="camp-card__scan"></p>
        <img src="/media/logo-wordmark.png" alt="CampBuddy" class="camp-card__brand-mark">
    </div>
    @if ($source ?? false)
        {{-- Only on a card made for someone else from public data (person-card.js). --}}
        <p class="camp-card__source">Made from public WordCamp info</p>
    @endif
    <div class="camp-card__tier-band" aria-hidden="true">
        <span>Code is Poetry</span>
    </div>
</div>
