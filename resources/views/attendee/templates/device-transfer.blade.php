{{--
    "Move my CampBuddy to this device" (device-transfer.js). Two sheets:
    tpl-transfer-request on the new device (its name is already linked
    elsewhere), tpl-transfer-approve on the device that holds the profile.
    Each has a few steps shown one at a time (data-step).
--}}
<template id="tpl-transfer-request">
    <dialog aria-labelledby="transfer-request-title">
        <div class="dialog-card">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;margin:-8px -8px 0 0">
                <p id="transfer-request-title" style="font-weight:700;margin:8px 0 4px">This name is linked on another device</p>
                <button type="button" class="topbar__icon-btn" data-action="close" aria-label="Close">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </div>
            <div style="display:flex;align-items:center;gap:10px;margin:6px 0 12px">
                <img data-slot="avatar" src="" alt="" width="36" height="36" style="border-radius:50%;object-fit:cover">
                <strong data-slot="name"></strong>
            </div>

            <div data-step="ask">
                <p class="footer-note" style="text-align:left;margin:0 0 12px">Set up CampBuddy on your laptop or another phone? Bring everything here: your Camp Card, discovery profile, saved sessions, quests and people to meet. The other device has to say yes, and it's cleared from there once it's here.</p>
                <p class="form-field__error" role="alert" style="margin:0 0 12px" data-transfer-error hidden></p>
                <div style="display:flex;gap:8px">
                    <button type="button" class="btn btn--outline" data-action="close" style="flex:1">Not now</button>
                    <button type="button" class="btn btn--primary" data-transfer-start style="flex:1">Bring it here</button>
                </div>
                <p class="footer-note" style="text-align:left;margin:12px 0 0">Not you? Ask an organizer.</p>
            </div>

            <div data-step="code" hidden>
                <p class="footer-note" style="text-align:left;margin:0 0 10px">Open CampBuddy on your other device. A request will show up there within half a minute. Type this code into it:</p>
                <p data-transfer-code style="font:800 2.2rem/1.1 ui-monospace,monospace;letter-spacing:.12em;text-align:center;margin:8px 0 12px" aria-live="polite"></p>
                <p class="footer-note" style="margin:0 0 12px">Waiting for your other device… Keep this screen open.</p>
                <button type="button" class="btn btn--outline btn--full" data-action="close">Cancel</button>
            </div>

            <div data-step="moving" hidden>
                <p class="footer-note" style="text-align:left;margin:0">Approved. Bringing your data here…</p>
            </div>

            <div data-step="done" hidden>
                <p style="font-weight:700;margin:0 0 6px">All here</p>
                <p class="footer-note" style="text-align:left;margin:0 0 12px">Your CampBuddy is on this device now, and cleared from the other one. Session reminders were set up for the other device: turn them on again in My Day if you want them here.</p>
                <button type="button" class="btn btn--primary btn--full" data-transfer-reload>Open it</button>
            </div>

            <div data-step="declined" hidden>
                <p class="footer-note" style="text-align:left;margin:0 0 12px">The other device said no, so nothing was moved.</p>
                <button type="button" class="btn btn--primary btn--full" data-action="close">Close</button>
            </div>

            <div data-step="expired" hidden>
                <p class="footer-note" style="text-align:left;margin:0 0 12px">The request ran out of time (or a newer one replaced it). Nothing was moved. Try again with CampBuddy open on your other device.</p>
                <button type="button" class="btn btn--primary btn--full" data-action="close">Close</button>
            </div>

            <div data-step="failed" hidden>
                <p class="footer-note" style="text-align:left;margin:0 0 12px">Something went wrong while moving it. Nothing was lost: it's still on your other device. Try again in a moment.</p>
                <button type="button" class="btn btn--primary btn--full" data-action="close">Close</button>
            </div>
        </div>
    </dialog>
</template>

<template id="tpl-transfer-approve">
    <dialog aria-labelledby="transfer-approve-title">
        <div class="dialog-card">
            <p id="transfer-approve-title" style="font-weight:700;margin:0 0 4px">Move your CampBuddy to another device?</p>
            <p class="footer-note" data-slot="device" style="text-align:left;margin:0 0 12px"></p>

            <form data-step="ask" data-transfer-form novalidate>
                <p class="footer-note" style="text-align:left;margin:0 0 12px">Someone picked your name on another device. If that's you, type the 6-digit code shown there. Your Camp Card, discovery profile, saved sessions, quests and people to meet move there and are cleared from here.</p>
                <div class="form-field">
                    <label class="form-field__label" for="transfer-code">Code from your new device</label>
                    <div class="form-input-wrap">
                        <input id="transfer-code" data-transfer-input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="123 456" style="letter-spacing:.12em">
                    </div>
                </div>
                <p class="form-field__error" role="alert" style="margin:0 0 12px" data-transfer-error hidden></p>
                <div style="display:flex;gap:8px">
                    <button type="button" class="btn btn--outline" data-transfer-decline style="flex:1">Not me</button>
                    <button type="submit" class="btn btn--primary" data-transfer-approve style="flex:1">Move it</button>
                </div>
            </form>

            <div data-step="sending" hidden>
                <p class="footer-note" style="text-align:left;margin:0 0 12px">Sent, locked so only your new device can open it. Waiting for it to arrive there… You can close this: it's cleared from here by itself once your new device has it.</p>
                <button type="button" class="btn btn--outline btn--full" data-action="close">Close</button>
            </div>

            <div data-step="moved" hidden>
                <p style="font-weight:700;margin:0 0 6px">Moved</p>
                <p class="footer-note" style="text-align:left;margin:0 0 12px">Your CampBuddy is on your new device now, and has been cleared from this one.</p>
                <button type="button" class="btn btn--primary btn--full" data-transfer-reload>OK</button>
            </div>

            <div data-step="not-moved" hidden>
                <p class="footer-note" style="text-align:left;margin:0 0 12px">Your new device didn't pick it up in time, so nothing changed here. Ask again from the new device.</p>
                <button type="button" class="btn btn--primary btn--full" data-action="close">Close</button>
            </div>
        </div>
    </dialog>
</template>
