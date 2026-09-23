{{--
    Attendee-discovery card (people.js → renderDiscoveryCard): the join
    prompt, join/edit form, "you're discoverable" status and matches list.
    Rendered on Home (compact) and on Explore → People (full), so both
    pages include this partial. Slot conventions: resources/js/attendee/template.js.
--}}

<template id="tpl-discovery-join-prompt">
    <div class="card">
        <p style="font-weight:700;margin:0 0 4px">Find people who match your interests</p>
        <p class="footer-note" style="text-align:left;margin:0 0 12px">
            Opt in to share a few tags under a random ID — never your name — and see who else at this event opted in too.
            You can leave any time.
        </p>
        <button type="button" class="btn btn--primary" id="join-discovery-btn">Join attendee discovery</button>
    </div>
</template>

<template id="tpl-discovery-join-form">
    <div class="card">
        <p style="font-weight:700;margin:0 0 8px"><span data-slot="verb">Join</span> attendee discovery</p>
        <div class="chip-group" id="join-tags" data-slot="tags"></div>
        <label class="field"><span>Profession (optional)</span><input type="text" id="join-profession" placeholder="e.g. Plugin developer" data-slot="profession"></label>
        <label class="field"><span>Who would you like to meet? (optional)</span><input type="text" id="join-who" placeholder="e.g. other agency owners" data-slot="who"></label>
        <button type="button" class="btn btn--primary btn--full" id="join-submit" data-slot="submit">Join</button>
    </div>
</template>

<template id="tpl-discovery-tag-chip">
    <button type="button" class="chip" data-slot="chip"></button>
</template>

<template id="tpl-discovery-status">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:start">
            <div>
                <p style="font-weight:700;margin:0">You're discoverable</p>
                <p class="footer-note" style="text-align:left;margin:2px 0 0" data-slot="tags"></p>
            </div>
            <div style="display:flex;gap:6px">
                <button type="button" class="btn btn--compact btn--outline" id="edit-discovery-btn">Edit</button>
                <button type="button" class="btn btn--compact btn--outline" id="leave-discovery-btn">Leave</button>
            </div>
        </div>
    </div>
</template>

{{-- Home's compact variant links through to the full experience on Explore --}}
<template id="tpl-discovery-explore-link">
    <a class="btn btn--outline btn--full" style="margin-top:10px" data-slot="link">See who matches your interests →</a>
</template>

{{-- Everything after the status card in the full (Explore) variant --}}
<template id="tpl-discovery-matches">
    <p class="notice" style="margin-top:10px" data-slot="offline">You're offline — matches will refresh when you're connected again.</p>

    <div style="margin-top:12px">
        <p class="footer-note" style="text-align:left" data-slot="empty">No matches yet — check back as more people join.</p>
        <slot data-slot="matches"></slot>
    </div>

    <div data-slot="met-section">
        <p class="u-eyebrow" style="margin-top:20px">People you've met</p>
        <slot data-slot="met"></slot>
    </div>
</template>

<template id="tpl-discovery-match">
    <div class="card" style="margin-bottom:10px">
        <div class="match-tags" data-slot="tags"></div>
        <p style="margin:8px 0 0;font-weight:600" data-slot="profession"></p>
        <p class="footer-note" style="text-align:left;margin:4px 0 0" data-slot="who-row">Wants to meet: <span data-slot="who"></span></p>
        <button type="button" class="btn btn--outline btn--compact" style="margin-top:10px" data-slot="met-btn">I met them</button>
        <p class="footer-note" style="text-align:left;margin-top:8px" data-slot="met-label">✓ Met</p>
    </div>
</template>

<template id="tpl-discovery-match-tag">
    <span class="match-tag" data-slot="tag"></span>
</template>
