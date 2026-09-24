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

<template id="tpl-deal-lead-dialog">
    <dialog>
        <div class="dialog-card">
            <p style="font-weight:700;margin:0 0 4px" data-slot="title"></p>
            <p class="footer-note" style="text-align:left;margin:0 0 14px">Share a few details and this deal will open right after — shared with the sponsor to process this deal.</p>
            <form data-lead-form>
                @include('attendee.partials.form-field', ['id' => 'lead-name', 'name' => 'name', 'label' => 'Name', 'required' => true, 'placeholder' => 'e.g. Priya Sharma', 'autocomplete' => 'name', 'maxlength' => 191, 'errorLine' => false])
                @include('attendee.partials.form-field', ['id' => 'lead-email', 'name' => 'email', 'type' => 'email', 'label' => 'Email', 'required' => true, 'placeholder' => 'you@example.com', 'autocomplete' => 'email', 'inputmode' => 'email', 'maxlength' => 191, 'errorLine' => false])
                @include('attendee.partials.form-field', ['id' => 'lead-mobile', 'name' => 'mobile', 'type' => 'tel', 'label' => 'Mobile', 'placeholder' => 'e.g. 98765 43210', 'autocomplete' => 'tel', 'inputmode' => 'tel', 'maxlength' => 32, 'hint' => 'Optional.', 'errorLine' => false])
                <p class="form-field__error" role="alert" style="margin:0 0 12px" data-lead-error hidden></p>
                <div style="display:flex;gap:8px;margin-top:4px">
                    <button type="button" class="btn btn--outline" data-action="close" style="flex:1">Cancel</button>
                    <button type="submit" class="btn btn--primary" style="flex:1">Continue</button>
                </div>
            </form>
        </div>
    </dialog>
</template>
