{{--
    Explore's dialogs: the in-app browser (in-app-browser.js) for sponsor
    and deal links, and the lead-capture form (deal-leads.js) shown before
    a lead-capturing deal opens. Slot conventions: resources/js/attendee/template.js.
--}}

<template id="tpl-in-app-browser">
    <dialog class="in-app-browser">
        <div class="in-app-browser__bar">
            <span class="in-app-browser__title" data-slot="title"></span>
            <div class="in-app-browser__actions">
                <a target="_blank" rel="noopener" class="topbar__icon-btn" aria-label="Open in browser" data-slot="open-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
                </a>
                <button type="button" class="topbar__icon-btn" data-action="close" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <div class="in-app-browser__body">
            {{-- sandbox: the framed site can run and submit forms and open new tabs, but not
                 navigate CampBuddy itself away (no allow-top-navigation) — a compromised
                 sponsor page can't redirect the app to a phishing page. --}}
            <iframe referrerpolicy="no-referrer" sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads" data-slot="frame"></iframe>
            <div class="in-app-browser__fallback" hidden>
                <p class="footer-note">This site couldn't be shown here.</p>
                <a target="_blank" rel="noopener" class="btn btn--primary" data-slot="fallback-link">Open in your browser →</a>
            </div>
        </div>
    </dialog>
</template>

{{--
    The contact form before a deal opens. Every field a deal may ask for is
    here; deal-leads.js keeps the ones that deal's form uses (its labels,
    hints and required marks come from the deal, DealForm) and adds one
    chip per product to tick. After sending, a deal that opens in a new tab
    shows the "done" step with a real link to tap, so no pop-up blocker can
    swallow it.
--}}
<template id="tpl-deal-lead-dialog">
    <dialog>
        <div class="dialog-card">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin:-8px -8px 0 0">
                <p style="font-weight:700;margin:8px 0 4px" data-slot="title"></p>
                <button type="button" class="topbar__icon-btn" data-action="close" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <form data-lead-form>
                <p class="footer-note" style="text-align:left;margin:0 0 14px" data-slot="intro">Share a few details to get this deal. They go only to the sponsor, so they can sort it out for you.</p>
                @foreach ([
                    ['name', 'text', 'e.g. Sunil Kumar Sharma', 'name', null, 191],
                    ['company', 'text', 'e.g. WPSimplified', 'organization', null, 191],
                    ['email', 'email', 'you@example.com', 'email', 'email', 191],
                    ['mobile', 'tel', 'e.g. 98765 43210', 'tel', 'tel', 32],
                ] as [$field, $type, $placeholder, $autocomplete, $inputmode, $max])
                    <div class="form-field" data-lead-field="{{ $field }}">
                        <label class="form-field__label" for="lead-{{ $field }}"><span data-lead-label></span><abbr class="form-field__req" title="required" data-lead-req>*</abbr></label>
                        <div class="form-input-wrap">
                            <input id="lead-{{ $field }}" name="{{ $field }}" type="{{ $type }}" placeholder="{{ $placeholder }}" autocomplete="{{ $autocomplete }}"
                                   @if ($inputmode) inputmode="{{ $inputmode }}" @endif maxlength="{{ $max }}" aria-describedby="lead-{{ $field }}-hint">
                        </div>
                        <p class="form-field__hint" id="lead-{{ $field }}-hint" data-lead-hint></p>
                    </div>
                @endforeach
                <div class="form-field" data-lead-field="choices" role="group" aria-labelledby="lead-choices-label">
                    <p class="form-field__label" id="lead-choices-label"><span data-lead-label></span><abbr class="form-field__req" title="required" data-lead-req>*</abbr></p>
                    <div class="chip-group" data-lead-choices></div>
                    <p class="form-field__hint" data-lead-hint></p>
                </div>
                <p class="form-field__error" role="alert" style="margin:0 0 12px" data-lead-error hidden></p>
                <div style="display:flex;gap:8px;margin-top:4px">
                    <button type="button" class="btn btn--outline" data-action="close" style="flex:1">Cancel</button>
                    <button type="submit" class="btn btn--primary" style="flex:1">Continue</button>
                </div>
            </form>
            <div data-lead-done hidden>
                <p class="footer-note" style="text-align:left;margin:0 0 14px">Thanks! Your details went to the sponsor. Open the deal to finish.</p>
                <div style="display:flex;gap:8px">
                    <button type="button" class="btn btn--outline" data-action="close" style="flex:1">Close</button>
                    <a target="_blank" rel="noopener sponsored" class="btn btn--primary" style="flex:1" data-slot="done-link">Open the deal ↗</a>
                </div>
            </div>
        </div>
    </dialog>
</template>

<template id="tpl-deal-lead-choice">
    <button type="button" class="chip" aria-pressed="false" data-slot="label"></button>
</template>
