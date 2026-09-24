{{--
    Attendee-discovery card (people.js → renderDiscoveryCard): the join
    prompt, join/edit form, "you're discoverable" status and matches list.
    Rendered on Home (compact) and on Explore → People (full), so both
    pages include this partial. Slot conventions: resources/js/attendee/template.js.
--}}

<template id="tpl-discovery-join-prompt">
    <div class="card discovery-prompt">
        <img class="discovery-prompt__art" src="/media/illustrations/people.svg" alt="" width="96" height="72">
        <div>
            <p style="font-weight:700;margin:0 0 4px">Find people who match your interests</p>
            <p class="footer-note" style="text-align:left;margin:0 0 12px">
                Pick a few interests and see who here shares them — with names and photos, so you can find each other.
                Leave any time.
            </p>
            <button type="button" class="btn btn--primary" id="join-discovery-btn">Join attendee discovery</button>
        </div>
    </div>
</template>

<template id="tpl-discovery-join-form">
    <div class="card">
        <p class="form-group__title"><span data-slot="verb">Join</span> attendee discovery</p>
        <p class="form-group__desc">Only what you fill in here is shared, and only with attendees of this event. You can edit or leave any time.</p>

        <fieldset class="form-field identity-choice">
            <legend class="form-field__label">How should people see you?</legend>
            <div class="identity-choice__options" role="radiogroup">
                <label class="identity-option"><input type="radio" name="identity" value="roster" data-slot="opt-roster"><span><strong>Pick my name</strong> from the attendee list</span></label>
                <label class="identity-option"><input type="radio" name="identity" value="typed"><span><strong>Type my name</strong> — I'm not on the list</span></label>
                <label class="identity-option"><input type="radio" name="identity" value="anonymous"><span><strong>Stay anonymous</strong> — interests only</span></label>
            </div>

            <div class="identity-panel" data-identity-panel="roster">
                <div data-roster-selected hidden class="roster-picked">
                    <img class="roster-row__avatar" alt="" width="40" height="40" data-fallback="/media/illustrations/avatar.svg" data-picked-avatar>
                    <span class="roster-picked__name" data-picked-name></span>
                    <button type="button" class="btn btn--compact btn--outline" data-picked-change>Change</button>
                </div>
                <div data-roster-search>
                    <label class="u-visually-hidden" for="join-roster-search">Search the attendee list for your name</label>
                    <input type="search" id="join-roster-search" class="search-input" placeholder="Start typing your name…" autocomplete="off">
                    <div class="roster-picker" data-roster-results role="listbox" aria-label="Matching attendees"></div>
                </div>
                <p class="form-field__hint">Your name, photo and links come from this WordCamp's public attendee list.</p>
            </div>

            <div class="identity-panel" data-identity-panel="typed" hidden>
                @include('attendee.partials.form-field', ['id' => 'join-display-name', 'label' => 'Your name', 'placeholder' => 'As on your badge', 'dataSlot' => 'display-name', 'maxlength' => 60, 'errorLine' => false])
            </div>
        </fieldset>

        <div class="form-field">
            <span class="form-field__label" id="join-tags-label">What describes you?</span>
            <div class="chip-group" id="join-tags" role="group" aria-labelledby="join-tags-label" data-slot="tags"></div>
            <p class="form-field__hint">Pick at least one, up to 5. People who share them show up first.</p>
        </div>

        @include('attendee.partials.form-field', ['id' => 'join-profession', 'label' => 'Profession', 'placeholder' => 'e.g. Plugin developer', 'hint' => 'Optional.', 'dataSlot' => 'profession', 'maxlength' => 100, 'errorLine' => false])
        @include('attendee.partials.form-field', ['id' => 'join-who', 'label' => 'Who would you like to meet?', 'placeholder' => 'e.g. other agency owners', 'hint' => 'Optional.', 'dataSlot' => 'who', 'maxlength' => 255, 'errorLine' => false])
        @include('attendee.partials.form-field', ['id' => 'join-wporg', 'label' => 'WordPress.org username', 'placeholder' => 'e.g. yourname', 'hint' => 'Optional — links to your profiles.wordpress.org page. You can paste the whole profile link.', 'dataSlot' => 'wporg', 'maxlength' => 120, 'errorLine' => false])

        <p class="form-error" data-join-error role="alert" hidden></p>
        <button type="button" class="btn btn--primary btn--full" id="join-submit" data-slot="submit">Join</button>
    </div>
</template>

<template id="tpl-roster-picker-row">
    <button type="button" class="roster-picker__row" role="option" data-slot="row">
        <img class="roster-row__avatar" alt="" width="32" height="32" loading="lazy" data-fallback="/media/illustrations/avatar.svg" data-slot="avatar">
        <span class="roster-picker__name" data-slot="name"></span>
        <span class="roster-picker__note" data-slot="note">Already linked</span>
    </button>
</template>

<template id="tpl-roster-picker-empty">
    <p class="footer-note" style="text-align:left;margin:8px 0 0" data-slot="text"></p>
</template>

<template id="tpl-discovery-tag-chip">
    <button type="button" class="chip" data-slot="chip"></button>
</template>

<template id="tpl-discovery-status">
    <div class="card discovery-status">
        <img class="discovery-status__avatar" alt="" width="44" height="44" data-fallback="/media/illustrations/avatar.svg" data-slot="avatar">
        <div class="discovery-status__body">
            <p style="font-weight:700;margin:0">You're discoverable<span data-slot="as-part"> as <span data-slot="name"></span></span></p>
            <p class="footer-note" style="text-align:left;margin:2px 0 0" data-slot="tags"></p>
        </div>
        <div class="discovery-status__actions">
            <button type="button" class="btn btn--compact btn--outline" id="edit-discovery-btn">Edit</button>
            <button type="button" class="btn btn--compact btn--outline" id="leave-discovery-btn">Leave</button>
        </div>
    </div>
</template>

{{-- Home's compact variant links through to the full experience on Explore --}}
<template id="tpl-discovery-explore-link">
    <a class="btn btn--outline btn--full" style="margin-top:10px" data-track="home_discovery_explore_click" data-slot="link">See who matches your interests →</a>
</template>

{{-- Everything after the status card in the full (Explore) variant --}}
<template id="tpl-discovery-matches">
    <p class="notice" style="margin-top:10px" data-slot="offline">You're offline — matches will refresh when you're connected again.</p>

    <div data-slot="mutual-section">
        <p class="u-eyebrow" style="margin-top:20px">🎉 You both want to meet</p>
        <p class="footer-note" style="text-align:left;margin:0 0 10px">You waved at each other, so you can see each other's names now. Nobody else can.</p>
        <slot data-slot="mutual"></slot>
    </div>

    <div data-slot="matches-section">
        <p class="u-eyebrow" style="margin-top:20px">Your best matches</p>
        <p class="footer-note" style="text-align:left;margin:0 0 10px">They share at least one interest with you. <strong>👋 Wave</strong> at someone — if they wave back, you both see each other's names, even if you joined anonymously.</p>
        <slot data-slot="matches"></slot>
    </div>

    <p class="footer-note" style="text-align:left;margin-top:16px" data-slot="empty">No one else has joined yet — check back later as more attendees sign up. Sharing your Camp Card helps too!</p>

    <div data-slot="others-section">
        <p class="u-eyebrow" style="margin-top:20px">Also open to meet</p>
        <slot data-slot="others"></slot>
    </div>

    <div data-slot="met-section">
        <p class="u-eyebrow" style="margin-top:20px">People you've met</p>
        <slot data-slot="met"></slot>
    </div>
</template>

<template id="tpl-discovery-match">
    <article class="person-card" data-slot="card">
        <p class="person-card__waved-you" data-slot="waved-you">👋 Wants to meet you — wave back to swap names</p>
        <div class="person-card__head">
            <img class="person-card__avatar" alt="" width="52" height="52" loading="lazy" data-fallback="/media/illustrations/avatar.svg" data-slot="avatar">
            <div class="person-card__who">
                <p class="person-card__name" data-slot="name"></p>
                <p class="person-card__verified" data-slot="verified">✓ On this WordCamp's attendee list</p>
                <p class="person-card__profession" data-slot="profession"></p>
            </div>
        </div>

        <p class="person-card__common" data-slot="common-row">You both: <strong data-slot="common"></strong></p>
        <div class="match-tags" data-slot="tags"></div>
        <p class="person-card__who-to-meet" data-slot="who-row">Wants to meet: <span data-slot="who"></span></p>
        <p class="person-card__message" data-slot="their-message"></p>
        <p class="person-card__my-message" data-slot="my-message"></p>

        <div class="person-card__actions">
            <a class="btn btn--compact btn--outline person-card__wporg" target="_blank" rel="noopener" data-track="discovery_profile_link_click" data-track-link-type="wporg" data-slot="wporg">
                <svg viewBox="0 0 24 24" width="15" height="15" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm-8.5 10c0-1.2.26-2.35.72-3.39l3.97 10.87A8.5 8.5 0 0 1 3.5 12Zm8.5 8.5c-.83 0-1.64-.12-2.4-.35l2.55-7.4 2.61 7.15.06.12c-.88.31-1.82.48-2.82.48Zm1.17-12.48c.51-.03.97-.08.97-.08.46-.06.4-.73-.05-.7 0 0-1.37.1-2.26.1-.83 0-2.23-.1-2.23-.1-.46-.03-.51.67-.06.7 0 0 .43.05.89.08l1.32 3.62-1.86 5.57-3.09-9.19c.51-.03.97-.08.97-.08.46-.06.4-.73-.05-.7 0 0-1.37.1-2.26.1l-.55-.01A8.49 8.49 0 0 1 17.8 5.8h-.1c-.83 0-1.42.73-1.42 1.51 0 .7.4 1.3.83 2 .33.57.71 1.3.71 2.36 0 .73-.28 1.58-.65 2.77l-.86 2.86-3.14-9.28Zm3.1 11.34 2.6-7.5c.48-1.21.64-2.18.64-3.04 0-.31-.02-.6-.06-.87A8.5 8.5 0 0 1 15.77 19.36Z"/></svg>
                WordPress.org
            </a>
            <slot data-slot="links"></slot>
            <button type="button" class="wave-btn" data-slot="wave"></button>
            <button type="button" class="meet-btn meet-btn--card" data-slot="meet"></button>
            <button type="button" class="btn btn--primary btn--compact person-card__met" data-slot="met-btn">I met them</button>
            <span class="person-card__met-label" data-slot="met-label">✓ Met</span>
        </div>
    </article>
</template>

<template id="tpl-discovery-match-tag">
    <span class="match-tag" data-slot="tag"></span>
</template>

{{-- 👋 Wave at a match (people.js). An anonymous profile gives the name to
    reveal — shown only if the other person waves back. --}}
<template id="tpl-wave-sheet">
    <dialog class="meet-sheet" aria-labelledby="wave-sheet-title">
        <form class="meet-sheet__card" method="dialog">
            <div class="meet-sheet__head">
                <span class="wave-sheet__emoji" aria-hidden="true">👋</span>
                <div class="meet-sheet__who">
                    <p class="meet-sheet__eyebrow">Wave at this match</p>
                    <h2 class="meet-sheet__name" id="wave-sheet-title" data-slot="title"></h2>
                </div>
                <button type="button" class="meet-sheet__close" data-wave-close aria-label="Close">×</button>
            </div>
            <p class="meet-sheet__sub" style="font-size:.875rem">If they wave back, you'll both see each other's names and messages. If not, nothing is revealed.</p>

            <div data-slot="name-field">
                <label class="meet-sheet__label" for="wave-name">Your first name</label>
                <input type="text" id="wave-name" maxlength="60" autocomplete="given-name" placeholder="Shown only if they wave back">
            </div>

            <div>
                <label class="meet-sheet__label" for="wave-message">Where to meet? <span style="font-weight:400;color:var(--muted)">(optional)</span></label>
                <input type="text" id="wave-message" maxlength="140" placeholder="e.g. By the coffee stand after the keynote">
            </div>

            <p class="meet-sheet__privacy" data-wave-error hidden style="color:var(--danger)"></p>

            <div class="meet-sheet__actions">
                <button type="button" class="btn btn--primary" data-wave-send>👋 Send wave</button>
            </div>
        </form>
    </dialog>
</template>
