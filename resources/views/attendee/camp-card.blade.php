<x-attendee-layout :event="$event">
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

                    <div class="camp-card camp-card--{{ $key }}" data-event-icon="{{ $event->faviconUrl() ?? '/media/favicon.png' }}">
                        <span class="camp-card__lanyard-hole" aria-hidden="true"></span>
                        <div class="camp-card__body">
                            <img src="{{ $event->faviconUrl() ?? '/media/favicon.png' }}" alt="{{ $event->display_name }}" class="camp-card__event-mark">
                            <img src="{{ $event->logoUrl() ?? '/media/logo.png' }}" alt="{{ $event->display_name }}" class="camp-card__event-mark-full">
                            <p class="camp-card__name"></p>
                            <p class="camp-card__role"></p>
                            <div class="camp-card__tags"></div>
                        </div>
                        <div class="camp-card__footer" hidden>
                            <div class="camp-card__qr-frame">
                                <canvas></canvas>
                            </div>
                            <img src="/media/icon.svg" alt="CampBuddy" class="camp-card__brand-mark">
                            <img src="/media/logo.svg" alt="CampBuddy" class="camp-card__brand-mark-full">
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

        <details style="margin-top:24px" open id="cc-edit-details">
            <summary class="section-head__title" style="cursor:pointer">Edit Camp Card</summary>

            <form id="camp-card-form" style="margin-top:12px">
                <label class="field"><span>Name (required)</span><input type="text" name="name" placeholder="e.g. Priya Sharma" required></label>
                <label class="field"><span>Role / title</span><input type="text" name="role" placeholder="e.g. WordPress Developer"></label>
                <label class="field"><span>Company / community</span><input type="text" name="company" placeholder="e.g. Acme Studio"></label>
                <label class="field"><span>City</span><input type="text" name="city" placeholder="e.g. Jaipur"></label>

                <div class="field">
                    <span>WordPress interests</span>
                    <div class="tag-input" id="interests-input">
                        <div class="tag-input__tags" id="interests-tags"></div>
                        <input type="text" id="interests-text" placeholder="Type an interest and press Enter">
                    </div>
                </div>

                <label class="field"><span>Ask me about</span><input type="text" name="askMeAbout" placeholder="e.g. Block themes"></label>

                <label class="field"><span>LinkedIn</span><input type="url" name="linkedin" placeholder="https://linkedin.com/in/…"></label>
                <label class="field"><span>Personal website</span><input type="url" name="website" placeholder="https://…"></label>
                <label class="field"><span>WordPress.org profile</span><input type="url" name="wordpressOrg" placeholder="https://profiles.wordpress.org/…"></label>
                <label class="field"><span>X / Twitter handle</span><input type="text" name="twitter" placeholder="yourhandle" maxlength="15" pattern="^@?[A-Za-z0-9_]{1,15}$"></label>

                <div class="field">
                    <span>QR code links to</span>
                    <div class="chip-group" id="qr-target-chips">
                        <button type="button" class="chip" data-qr-target="linkedin">LinkedIn</button>
                        <button type="button" class="chip" data-qr-target="website">Website</button>
                        <button type="button" class="chip" data-qr-target="wordpressOrg">WordPress.org</button>
                        <button type="button" class="chip" data-qr-target="twitter">X / Twitter</button>
                    </div>
                </div>

                <div class="field">
                    <span>Show on my visible card</span>
                    <div class="chip-group" id="visible-fields-chips">
                        @foreach (['role' => 'Role', 'company' => 'Company', 'city' => 'City', 'interests' => 'Interests', 'askMeAbout' => 'Ask me about'] as $key => $label)
                            <button type="button" class="chip" data-visible-field="{{ $key }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="btn btn--primary btn--full">Save Camp Card</button>
            </form>
        </details>

        <div id="data-controls" style="margin-top:24px"></div>
    </main>
</x-attendee-layout>
