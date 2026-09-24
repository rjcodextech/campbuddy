<x-attendee-layout :event="$event" title="Camp Card">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        <div class="section-head">
            <h1 class="section-head__title">Camp Card</h1>
        </div>

        <p id="camp-card-sample-note" class="footer-note" style="text-align:left" hidden>
            This is a preview — fill in the form below to make it yours.
        </p>

        <div class="camp-card-scroll" id="camp-card-scroll">
            @foreach (['classic' => 'Classic', 'minimal' => 'Minimal', 'bold' => 'Bold', 'split' => 'Split', 'badge' => 'Badge', 'pass' => 'Pass'] as $key => $label)
                <div class="camp-card-scroll__item" data-layout-card="{{ $key }}">
                    <p class="camp-card-scroll__label">{{ $label }}</p>

                    <div class="camp-card camp-card--{{ $key }}" data-event-icon="{{ $event->faviconUrl() ?? '/media/icons/icon-192.png' }}">
                        <span class="camp-card__lanyard-hole" aria-hidden="true"></span>
                        <div class="camp-card__body">
                            <img src="{{ $event->faviconUrl() ?? '/media/icons/icon-192.png' }}" alt="{{ $event->display_name }}" class="camp-card__event-mark" data-fallback="/media/icons/icon-192.png">
                            <img src="{{ $event->logoUrl() ?? '/media/logo-wordmark.png' }}" alt="{{ $event->display_name }}" class="camp-card__event-mark-full" data-fallback="/media/logo-wordmark.png">
                            <p class="camp-card__name"></p>
                            <p class="camp-card__role"></p>
                            <div class="camp-card__tags"></div>
                        </div>
                        <div class="camp-card__footer" hidden>
                            <div class="camp-card__qr-frame">
                                <canvas></canvas>
                            </div>
                            <img src="/media/logo-wordmark.png" alt="CampBuddy" class="camp-card__brand-mark">
                        </div>
                        <div class="camp-card__tier-band" aria-hidden="true">
                            <span>Code is Poetry</span>
                        </div>
                    </div>

                    <div class="qr__actions">
                        <button type="button" class="btn btn--outline btn--compact" data-share-card="{{ $key }}">Share</button>
                        <button type="button" class="btn btn--outline btn--compact" data-download-card="{{ $key }}">Download</button>
                    </div>
                </div>
            @endforeach
        </div>

        <details class="cc-editor" open id="cc-edit-details">
            <summary class="cc-editor__summary">
                <span class="section-head__title">Edit Camp Card</span>
                <svg class="cc-editor__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
            </summary>

            <p class="cc-editor__privacy">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
                Saved on this device only. Nothing you type here is sent to CampBuddy.
            </p>

            {{-- novalidate: the required fields are checked and highlighted by camp-card.js
                 (native validation bubbles don't show at all on iOS Safari). --}}
            <form id="camp-card-form" class="cc-form" novalidate>
                <fieldset class="form-group">
                    <legend class="form-group__title">About you</legend>
                    <p class="form-group__desc">This is what shows on your card.</p>

                    @include('attendee.partials.form-field', ['id' => 'cc-name', 'name' => 'name', 'label' => 'Name', 'required' => true, 'placeholder' => 'e.g. Priya Sharma', 'autocomplete' => 'name', 'maxlength' => 60, 'hint' => 'Shown large at the top of your card.'])
                    @include('attendee.partials.form-field', ['id' => 'cc-role', 'name' => 'role', 'label' => 'Role or title', 'placeholder' => 'e.g. WordPress Developer', 'autocomplete' => 'organization-title', 'maxlength' => 60])
                    @include('attendee.partials.form-field', ['id' => 'cc-company', 'name' => 'company', 'label' => 'Company or community', 'placeholder' => 'e.g. Acme Studio', 'autocomplete' => 'organization', 'maxlength' => 60])
                    @include('attendee.partials.form-field', ['id' => 'cc-city', 'name' => 'city', 'label' => 'City', 'placeholder' => 'e.g. Jaipur', 'autocomplete' => 'address-level2', 'maxlength' => 40])
                </fieldset>

                <fieldset class="form-group">
                    <legend class="form-group__title">Your interests</legend>
                    <p class="form-group__desc">So people know what to talk to you about.</p>

                    <div class="form-field">
                        <label class="form-field__label" for="interests-text">WordPress interests</label>

                        <div class="cc-tags" id="interests-input">
                            <div class="cc-tags__items" id="interests-tags"></div>
                            <input class="cc-tags__input" type="text" id="interests-text" placeholder="Add an interest" maxlength="30"
                                   enterkeyhint="done" autocomplete="off" aria-describedby="interests-hint">
                            <button type="button" class="cc-tags__add" id="interests-add" hidden>Add</button>
                        </div>

                        <p class="form-field__hint" id="interests-hint" aria-live="polite">
                            <span id="interests-count">0</span> of 8 added. Press Enter or comma after each one.
                        </p>

                        <div class="cc-suggest" id="interests-suggest" role="group" aria-label="Suggested interests"></div>
                    </div>

                    @include('attendee.partials.form-field', ['id' => 'cc-askMeAbout', 'name' => 'askMeAbout', 'label' => 'Ask me about', 'placeholder' => 'e.g. Block themes', 'maxlength' => 80, 'hint' => 'A conversation starter for people who see your card.'])
                </fieldset>

                <fieldset class="form-group" id="cc-links-group">
                    <legend class="form-group__title">Find me online<abbr class="form-field__req" title="at least one is required">*</abbr></legend>
                    <p class="form-group__desc">Add at least one — your QR code points to one of them.</p>
                    <p class="form-field__error form-group__error" id="cc-links-error" role="alert" hidden>Add at least one link so your QR code has somewhere to go.</p>

                    @include('attendee.partials.form-field', ['id' => 'cc-linkedin', 'name' => 'linkedin', 'label' => 'LinkedIn', 'link' => true, 'inputmode' => 'url', 'placeholder' => 'linkedin.com/in/your-name'])
                    @include('attendee.partials.form-field', ['id' => 'cc-website', 'name' => 'website', 'label' => 'Personal website', 'link' => true, 'inputmode' => 'url', 'placeholder' => 'yoursite.com'])
                    @include('attendee.partials.form-field', ['id' => 'cc-wordpressOrg', 'name' => 'wordpressOrg', 'label' => 'WordPress.org profile', 'link' => true, 'inputmode' => 'url', 'placeholder' => 'profiles.wordpress.org/username'])
                    @include('attendee.partials.form-field', ['id' => 'cc-twitter', 'name' => 'twitter', 'label' => 'X / Twitter', 'handle' => true, 'prefix' => '@', 'placeholder' => 'yourhandle', 'autocomplete' => 'off', 'hint' => 'Just your handle — pasting a profile link works too.'])
                </fieldset>

                <fieldset class="form-group">
                    <legend class="form-group__title">On your card</legend>
                    <p class="form-group__desc">Choose what's visible and where your QR code goes.</p>

                    <div class="form-field">
                        <span class="form-field__label" id="qr-target-label">QR code links to</span>
                        <div class="chip-group" id="qr-target-chips" role="group" aria-labelledby="qr-target-label">
                            <button type="button" class="chip" aria-pressed="false" data-qr-target="linkedin">LinkedIn</button>
                            <button type="button" class="chip" aria-pressed="false" data-qr-target="website">Website</button>
                            <button type="button" class="chip" aria-pressed="false" data-qr-target="wordpressOrg">WordPress.org</button>
                            <button type="button" class="chip" aria-pressed="false" data-qr-target="twitter">X / Twitter</button>
                        </div>
                        <p class="form-field__hint">Faded options need their link filled in above.</p>
                    </div>

                    <div class="form-field">
                        <span class="form-field__label" id="visible-fields-label">Show on my card</span>
                        <div class="chip-group" id="visible-fields-chips" role="group" aria-labelledby="visible-fields-label">
                            @foreach (['role' => 'Role', 'company' => 'Company', 'city' => 'City', 'interests' => 'Interests', 'askMeAbout' => 'Ask me about'] as $key => $label)
                                <button type="button" class="chip" aria-pressed="false" data-visible-field="{{ $key }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        <p class="form-field__hint">Your name and QR code always show.</p>
                    </div>
                </fieldset>

                <p class="form-field__hint"><abbr class="form-field__req" title="required">*</abbr> Needed to generate your card: your name and at least one link.</p>

                <button type="submit" class="btn btn--primary btn--full cc-form__save" id="cc-save">Save Camp Card</button>
            </form>
        </details>

        <div id="data-controls" style="margin-top:24px">
            @include('attendee.partials.data-controls')
        </div>
    </main>

    @include('attendee.templates.camp-card')
</x-attendee-layout>
