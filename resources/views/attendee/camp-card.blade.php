<x-attendee-layout :event="$event" title="Camp Card">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        <div class="section-head">
            <h1 class="section-head__title">Camp Card</h1>
        </div>

        {{-- Verified Attendee Claim Profile Banner --}}
        <div class="profile-claim-banner" id="profile-claim-banner">
            <div class="profile-claim-banner__left">
                <x-attendee.line-icon name="shield-check" />
                <div>
                    <h3 class="profile-claim-banner__title" id="profile-claim-title">Verified Attendee Profile</h3>
                    <p class="profile-claim-banner__desc" id="profile-claim-desc">Link your CampCard to your public attendee listing.</p>
                </div>
            </div>
            <a href="#cc-share" class="profile-claim-banner__btn" id="profile-claim-btn">Claim Profile</a>
        </div>

        @php($layouts = ['ticket' => 'Ticket', 'classic' => 'Classic', 'minimal' => 'Minimal', 'bold' => 'Bold', 'split' => 'Split', 'badge' => 'Badge', 'pass' => 'Pass'])
        <div class="cc-studio">
            <div class="cc-studio__preview">
                <div class="cc-style-pills" id="cc-style-pills" role="group" aria-label="Jump to card style">
                    @foreach ($layouts as $key => $label)
                        <button type="button" class="cc-style-pill {{ $loop->first ? 'is-active' : '' }}" data-layout-jump="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>

                <div class="camp-card-scroll" id="camp-card-scroll">
                    @foreach ($layouts as $key => $label)
                        <div class="camp-card-scroll__item" data-layout-card="{{ $key }}">
                            <p class="camp-card-scroll__label">{{ $label }}</p>

                            @include('attendee.partials.camp-card-face', ['key' => $key, 'event' => $event])

                            {{-- Clear visual hierarchy: Share leads as primary, Download as secondary. --}}
                            <div class="qr__actions">
                                <button type="button" class="btn btn--primary" data-share-card="{{ $key }}"><x-attendee.line-icon name="share" /><span data-export-label>Share</span></button>
                                <button type="button" class="btn btn--outline" data-download-card="{{ $key }}"><x-attendee.line-icon name="download" /><span data-export-label>Download</span></button>
                            </div>
                        </div>
                    @endforeach
                </div>

                {{-- Active Social Badges Dock --}}
                <div class="social-badges-dock" id="cc-social-badges"></div>

                {{-- Under the cards, not above them: it appears once camp-card.js has looked for a saved card,
                     and above the cards it pushed the whole gallery down as it came in (layout shift). --}}
                <p id="camp-card-sample-note" class="footer-note" style="text-align:left" hidden>
                    This is a preview. Fill in the form <span class="cc-note-beside">or switch styles</span> to make it yours.
                </p>
                <p class="cc-print-note">
                    <strong>Print quality:</strong> 900&nbsp;DPI, sized for a 3&nbsp;×&nbsp;5&nbsp;in card.
                </p>
            </div>

            <div class="cc-studio__editor">
                <details class="cc-editor" open id="cc-edit-details">
            <summary class="cc-editor__summary">
                <span class="section-head__title">Edit Camp Card</span>
                <svg class="cc-editor__chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
            </summary>

            <p class="cc-editor__privacy">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></svg>
                Stored locally on this device. Shared only when you enable the attendee-list toggle below.
            </p>

            {{-- novalidate: the required fields are checked and highlighted by camp-card.js
                 (native validation bubbles don't show at all on iOS Safari). --}}
            <form id="camp-card-form" class="cc-form" novalidate>
                <fieldset class="form-group">
                    <legend class="form-group__title">About you</legend>

                    @include('attendee.partials.form-field', ['id' => 'cc-name', 'name' => 'name', 'label' => 'Name', 'required' => true, 'placeholder' => 'e.g. Sunil Kumar Sharma', 'autocomplete' => 'name', 'maxlength' => 60, 'hint' => 'Shown large at the top of your card.'])
                    @include('attendee.partials.form-field', ['id' => 'cc-role', 'name' => 'role', 'label' => 'Role or title', 'placeholder' => 'e.g. WordPress Engineer', 'autocomplete' => 'organization-title', 'maxlength' => 60])
                    @include('attendee.partials.form-field', ['id' => 'cc-company', 'name' => 'company', 'label' => 'Company or community', 'placeholder' => 'e.g. WPSimplified', 'autocomplete' => 'organization', 'maxlength' => 60])
                    @include('attendee.partials.form-field', ['id' => 'cc-city', 'name' => 'city', 'label' => 'City', 'placeholder' => 'e.g. Jaipur', 'autocomplete' => 'address-level2', 'maxlength' => 40])
                </fieldset>

                <fieldset class="form-group">
                    <legend class="form-group__title">Your interests</legend>

                    <div class="form-field">
                        <label class="form-field__label" for="interests-text">WordPress interests</label>

                        <div class="cc-tags" id="interests-input">
                            <div class="cc-tags__items" id="interests-tags"></div>
                            <input class="cc-tags__input" type="text" id="interests-text" placeholder="Add an interest" maxlength="30"
                                   enterkeyhint="done" autocomplete="off" aria-describedby="interests-hint">
                            <button type="button" class="cc-tags__add" id="interests-add" hidden data-track="camp_card_customize" data-track-target="interest_add">Add</button>
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
                    <p class="form-group__desc">Add at least one. Your QR code points to one of them.</p>
                    <p class="form-field__error form-group__error" id="cc-links-error" role="alert" hidden>Add at least one link so your QR code has somewhere to go.</p>

                    @include('attendee.partials.form-field', ['id' => 'cc-linkedin', 'name' => 'linkedin', 'label' => 'LinkedIn', 'link' => true, 'inputmode' => 'url', 'placeholder' => 'linkedin.com/in/your-name'])
                    @include('attendee.partials.form-field', ['id' => 'cc-website', 'name' => 'website', 'label' => 'Personal website', 'link' => true, 'inputmode' => 'url', 'placeholder' => 'yoursite.com'])
                    @include('attendee.partials.form-field', ['id' => 'cc-wordpressOrg', 'name' => 'wordpressOrg', 'label' => 'WordPress.org profile', 'link' => true, 'inputmode' => 'url', 'placeholder' => 'profiles.wordpress.org/username'])
                    @include('attendee.partials.form-field', ['id' => 'cc-twitter', 'name' => 'twitter', 'label' => 'X / Twitter', 'handle' => true, 'prefix' => '@', 'placeholder' => 'yourhandle', 'autocomplete' => 'off', 'hint' => 'Just your handle. Pasting a profile link works too.'])
                </fieldset>

                <fieldset class="form-group">
                    <legend class="form-group__title">On your card</legend>
                    <p class="form-group__desc">Choose what's visible and where your QR code goes.</p>

                    <div class="form-field">
                        <span class="form-field__label" id="qr-target-label">QR code links to</span>
                        <div class="chip-group" id="qr-target-chips" role="group" aria-labelledby="qr-target-label">
                            <button type="button" class="chip" aria-pressed="false" data-qr-target="linkedin" data-track="camp_card_customize" data-track-target="qr_linkedin">LinkedIn</button>
                            <button type="button" class="chip" aria-pressed="false" data-qr-target="website" data-track="camp_card_customize" data-track-target="qr_website">Website</button>
                            <button type="button" class="chip" aria-pressed="false" data-qr-target="wordpressOrg" data-track="camp_card_customize" data-track-target="qr_wordpressOrg">WordPress.org</button>
                            <button type="button" class="chip" aria-pressed="false" data-qr-target="twitter" data-track="camp_card_customize" data-track-target="qr_twitter">X / Twitter</button>
                        </div>
                        <p class="form-field__hint">Faded options need their link filled in above.</p>
                    </div>

                    <div class="form-field">
                        <span class="form-field__label" id="visible-fields-label">Show on my card</span>
                        <div class="chip-group" id="visible-fields-chips" role="group" aria-labelledby="visible-fields-label">
                            @foreach (['role' => 'Role', 'company' => 'Company', 'city' => 'City', 'interests' => 'Interests', 'askMeAbout' => 'Ask me about'] as $key => $label)
                                <button type="button" class="chip" aria-pressed="false" data-visible-field="{{ $key }}" data-track="camp_card_customize" data-track-target="field_{{ $key }}">{{ $label }}</button>
                            @endforeach
                        </div>
                        <p class="form-field__hint">Your name and QR code always show.</p>
                    </div>
                </fieldset>

                <p class="form-field__hint"><abbr class="form-field__req" title="required">*</abbr> Needed to generate your card: your name and at least one link.</p>

                <button type="submit" class="btn btn--primary btn--full cc-form__save" id="cc-save">Save Camp Card</button>
            </form>
        </details>

        {{-- card-share.js: off until the attendee turns it on; then only what is ON the card is shared. --}}
        <section class="card cc-share" id="cc-share" aria-labelledby="cc-share-title">
            <div class="cc-share__head">
                <div class="cc-share__text">
                    <h2 class="cc-share__title" id="cc-share-title">Show my Camp Card on the attendee list</h2>
                    <p class="cc-share__desc">Shares only your visible card fields and QR link with event attendees. Toggle off anytime.</p>
                </div>
                <label class="cc-share__switch">
                    <input type="checkbox" id="cc-share-toggle" role="switch">
                    <span class="cc-share__slider" aria-hidden="true"></span>
                    <span class="u-visually-hidden">Show my Camp Card on the attendee list</span>
                </label>
            </div>
            <div id="cc-share-body" aria-live="polite"></div>
        </section>
            </div>
        </div>

        <div id="data-controls" style="margin-top:24px">
            @include('attendee.partials.data-controls')
        </div>
        <div id="your-choices" style="margin-top:24px">
            @include('attendee.partials.notification-choices')
        </div>
    </main>

    @include('attendee.templates.camp-card')
</x-attendee-layout>
