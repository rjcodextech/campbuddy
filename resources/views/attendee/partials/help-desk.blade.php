{{-- Docked Safety & Help Desk Bar + Modal (shown across all event PWA/mobile views, above BottomBar) --}}
<div class="sticky-help-bar" id="sticky-help-bar">
    <button type="button" class="sticky-help-bar__btn" id="sticky-help-btn" aria-label="Safety &amp; Help Desk" data-track="help_sheet_open">
        <div class="sticky-help-bar__left">
            <x-attendee.line-icon name="shield" />
            <div>
                <div class="sticky-help-bar__title">Safety &amp; Help Desk</div>
                <div class="sticky-help-bar__subtitle">Venue support &amp; confidential reporting</div>
            </div>
        </div>
        <span class="sticky-help-bar__badge">Emergency</span>
    </button>
</div>

<div class="help-modal" id="help-modal" role="dialog" aria-modal="true" aria-labelledby="help-modal-title" hidden>
    <div class="help-modal__sheet">
        <div class="help-modal__header">
            <h3 class="help-modal__title" id="help-modal-title">Support &amp; Safety</h3>
            <button type="button" class="help-modal__close" id="help-modal-close" aria-label="Close help sheet">
                <x-attendee.line-icon name="x" />
            </button>
        </div>

        <div class="help-modal__content">
            <div class="help-modal__card help-modal__card--danger">
                <h4 class="help-modal__card-title">
                    <x-attendee.line-icon name="shield" />
                    Need Immediate Help?
                </h4>
                <p class="help-modal__card-desc">
                    Connect directly with the venue safety &amp; info desk team anytime during the event.
                </p>
                <a href="{{ route('event.explore', $event) }}#info" class="help-modal__cta-white">
                    Venue &amp; Organizer Contact
                </a>
            </div>

            <div class="help-modal__card help-modal__card--peach">
                <h4 class="help-modal__card-title">
                    <x-attendee.line-icon name="life-buoy" />
                    Venue Info &amp; Rooms
                </h4>
                <p class="help-modal__card-desc">
                    Registration &amp; First Aid: Main Entrance Lobby. Quiet &amp; Accessibility Support: Ask any volunteer badge.
                </p>
            </div>

            <div class="help-modal__card">
                <h4 class="help-modal__card-title">
                    <x-attendee.line-icon name="shield-check" />
                    Safety Code &amp; DPDP Act 2023 Rights
                </h4>
                <p class="help-modal__card-desc">
                    Read our Harassment-Free Code of Conduct or inspect and clear data saved on this device.
                </p>
                <div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:4px;">
                    <a href="{{ route('terms') }}" class="btn btn--primary btn--compact">Safety Code</a>
                    <a href="{{ route('privacy') }}" class="btn btn--outline btn--compact">Privacy &amp; Data</a>
                </div>
            </div>
        </div>
    </div>
</div>
