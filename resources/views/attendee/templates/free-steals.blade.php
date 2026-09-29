{{--
    "Suggest a Free Steal" (free-steal-suggest.js). Sent to
    POST /api/v1/events/{slug}/free-steal-suggestions and reviewed by an
    admin before anything is added. `website` is a trap for bots: hidden
    from people and screen readers, and a filled one is quietly dropped.
--}}
<template id="tpl-free-steal-suggest">
    <dialog>
        <div class="dialog-card">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin:-8px -8px 0 0">
                <p style="font-weight:700;margin:8px 0 4px">Suggest a Free Steal</p>
                <button type="button" class="topbar__icon-btn" data-action="close" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form data-suggest-form novalidate>
                <p class="footer-note" style="text-align:left;margin:0 0 14px">A free WordPress plugin or tool you made or love. The CampBuddy team looks at every one before anything is added.</p>
                @foreach ([
                    ['name', 'text', 'Name of the tool', 'e.g. The Off Switch', true, 120],
                    ['url', 'url', 'Link', 'https://github.com/… or wordpress.org/plugins/…', true, 500],
                    ['maker', 'text', 'Who made it', 'A person or a company', false, 120],
                ] as [$field, $type, $label, $placeholder, $required, $max])
                    <div class="form-field">
                        <label class="form-field__label" for="suggest-{{ $field }}">{{ $label }}@if ($required)<abbr class="form-field__req" title="required">*</abbr>@endif</label>
                        <div class="form-input-wrap">
                            <input id="suggest-{{ $field }}" name="{{ $field }}" type="{{ $type }}" placeholder="{{ $placeholder }}" maxlength="{{ $max }}" @if ($required) required @endif
                                   @if ($type === 'url') inputmode="url" autocomplete="url" @else autocomplete="off" @endif>
                        </div>
                    </div>
                @endforeach
                <div class="form-field">
                    <label class="form-field__label" for="suggest-why">Why it's worth a look</label>
                    <textarea id="suggest-why" name="why" maxlength="300" rows="3" placeholder="What it does, in a line or two"></textarea>
                </div>
                <div class="form-field">
                    <label class="form-field__label" for="suggest-email">Your email</label>
                    <div class="form-input-wrap">
                        <input id="suggest-email" name="email" type="email" inputmode="email" autocomplete="email" maxlength="191" placeholder="you@example.com" aria-describedby="suggest-email-hint">
                    </div>
                    <p class="form-field__hint" id="suggest-email-hint">Optional. Only so the team can ask you about it.</p>
                </div>
                <div class="u-visually-hidden" aria-hidden="true">
                    <label for="suggest-website">Leave this empty</label>
                    <input id="suggest-website" name="website" type="text" tabindex="-1" autocomplete="off">
                </div>
                <p class="form-field__error" role="alert" style="margin:0 0 12px" data-suggest-error hidden></p>
                <div style="display:flex;gap:8px;margin-top:4px">
                    <button type="button" class="btn btn--outline" data-action="close" style="flex:1">Cancel</button>
                    <button type="submit" class="btn btn--primary" style="flex:1">Send</button>
                </div>
            </form>
            <div data-suggest-done hidden>
                <p class="footer-note" style="text-align:left;margin:0 0 14px">Thanks! The team will take a look.</p>
                <button type="button" class="btn btn--primary btn--full" data-action="close">Close</button>
            </div>
        </div>
    </dialog>
</template>
