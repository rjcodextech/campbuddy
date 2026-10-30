{{--
    The consent tick before anything is shared (DPDP Act 2023 s.6; s.9 for under-18s). Server side:
    App\Support\Consent (the `consent` field, logged with the notice version). Same words in the app
    (native-app … ui/ConsentCheck.kt). Styles: components/_form.scss (.consent-check).

    Required: $id, $what — what is shared, and with whom ("these details with attendees of this event")
--}}
<label class="consent-check" for="{{ $id }}">
    <input type="checkbox" id="{{ $id }}" class="consent-check__box" data-consent>
    <span class="consent-check__text">
        I agree to share {{ $what }}, as the <a href="{{ route('privacy') }}" target="_blank" rel="noopener">Privacy notice</a> explains, and I'm 18 or older (or my parent or guardian agrees). I can leave and delete it any time.
    </span>
</label>
