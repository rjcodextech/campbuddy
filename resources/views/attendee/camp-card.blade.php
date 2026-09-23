<x-attendee-layout :event="$event">
    @include('attendee.partials.topbar')

    <main id="main-content" tabindex="-1">
        <div class="section-head">
            <h1 class="section-head__title">Camp Card</h1>
        </div>

        <div id="camp-card-empty" class="card" style="text-align:center" hidden>
            <p class="mission-big">Your Camp Card is almost ready</p>
            <p class="footer-note">Add your name and a link you'd like people to scan.</p>
        </div>

        <div id="camp-card-preview" class="camp-card" hidden>
            <span class="camp-card__label">Camp Card</span>
            <p class="camp-card__name" id="cc-name"></p>
            <p class="camp-card__role" id="cc-role"></p>
            <div class="camp-card__tags" id="cc-tags"></div>
        </div>

        <div id="qr-section" class="qr" hidden>
            <div class="qr__box"><canvas id="qr-canvas"></canvas></div>
            <div class="qr__actions">
                <button type="button" class="btn btn--ghost" id="fullscreen-btn">View fullscreen</button>
            </div>
        </div>

        <details style="margin-top:24px" open id="cc-edit-details">
            <summary class="section-head__title" style="cursor:pointer">Edit Camp Card</summary>

            <form id="camp-card-form" style="margin-top:12px">
                <label class="field"><span>Name (required)</span><input type="text" name="name" required></label>
                <label class="field"><span>Role / title</span><input type="text" name="role"></label>
                <label class="field"><span>Company / community</span><input type="text" name="company"></label>
                <label class="field"><span>City</span><input type="text" name="city"></label>
                <label class="field"><span>WordPress interests</span><input type="text" name="interests" placeholder="e.g. Gutenberg, WooCommerce"></label>
                <label class="field"><span>Ask me about</span><input type="text" name="askMeAbout"></label>

                <label class="field"><span>LinkedIn</span><input type="url" name="linkedin" placeholder="https://linkedin.com/in/…"></label>
                <label class="field"><span>Personal website</span><input type="url" name="website"></label>
                <label class="field"><span>WordPress.org profile</span><input type="url" name="wordpressOrg" placeholder="https://profiles.wordpress.org/…"></label>
                <label class="field"><span>X / Twitter</span><input type="url" name="twitter"></label>

                <div class="field">
                    <span>QR code links to</span>
                    <select name="primaryLink" id="primary-link-select">
                        <option value="linkedin">LinkedIn</option>
                        <option value="website">Personal website</option>
                        <option value="wordpressOrg">WordPress.org profile</option>
                        <option value="twitter">X / Twitter</option>
                    </select>
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
