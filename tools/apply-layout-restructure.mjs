import fs from 'fs';

// =========================================================================
// 1. UPDATE welcome.blade.php
// =========================================================================
let welcome = fs.readFileSync('resources/views/welcome.blade.php', 'utf8');

// Replace topbar with topbar__inner
const oldWelcomeTopbar = `        <header class="topbar">
            <a href="{{ route('home') }}" class="brand" data-track="home_link_click" data-track-target="brand">
                <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
            </a>

            <div class="topbar__actions">
                <a href="{{ route('about') }}" class="topbar__text-btn" data-track="about_open" data-track-surface="topbar">
                    <span>About</span>
                </a>
        <button type="button" id="open-on-phone-btn" class="topbar__text-btn" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="7" y="2" width="10" height="20" rx="2" />
                <path d="M11 18h2" />
            </svg>
            <span>Open on phone</span>
        </button>

                <button type="button" id="install-app-btn" class="topbar__text-btn" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3v12" />
                        <path d="M7 10l5 5 5-5" />
                        <path d="M5 21h14" />
                    </svg>
                    <span>Install app</span>
                </button>
            </div>
        </header>`;

const newWelcomeTopbar = `        <header class="topbar">
            <div class="topbar__inner">
                <a href="{{ route('home') }}" class="brand" data-track="home_link_click" data-track-target="brand">
                    <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
                </a>

                <div class="topbar__actions">
                    <a href="{{ route('about') }}" class="topbar__text-btn" data-track="about_open" data-track-surface="topbar">
                        <span>About</span>
                    </a>
                    <button type="button" id="open-on-phone-btn" class="topbar__text-btn" hidden>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="7" y="2" width="10" height="20" rx="2" />
                            <path d="M11 18h2" />
                        </svg>
                        <span>Open on phone</span>
                    </button>

                    <button type="button" id="install-app-btn" class="topbar__text-btn" hidden>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 3v12" />
                            <path d="M7 10l5 5 5-5" />
                            <path d="M5 21h14" />
                        </svg>
                        <span>Install app</span>
                    </button>
                </div>
            </div>
        </header>`;

welcome = welcome.replace(oldWelcomeTopbar, newWelcomeTopbar);

// Replace hero action buttons and add trust row
const oldHeroActions = `                    <div class="landing-hero__actions">
                        <a class="btn btn--primary" href="#find-your-camp" data-track="picker_cta" data-track-target="choose">
                            <span>Choose your WordCamp</span>
                            <span aria-hidden="true">&darr;</span>
                        </a>
                        <a class="btn btn--outline" href="{{ route('guide') }}" data-track="guide_open" data-track-surface="picker">
                            <span>First WordCamp?</span>
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                </div>`;

const newHeroActions = `                    <div class="landing-hero__actions">
                        <a class="btn btn--primary btn--hero-cta" href="#find-your-camp" data-track="picker_cta" data-track-target="choose">
                            <span>Choose your WordCamp</span>
                            <span aria-hidden="true">&darr;</span>
                        </a>
                        <a class="btn btn--outline btn--hero-guide" href="{{ route('guide') }}" data-track="guide_open" data-track-surface="picker">
                            <span>First WordCamp?</span>
                            <span aria-hidden="true">&rarr;</span>
                        </a>
                    </div>
                    <div class="hero-trust-bar">
                        <span><x-attendee.line-icon name="lock" /> DPDPA 2023 Compliant</span>
                        <span class="hero-trust-bar__sep">&middot;</span>
                        <span><x-attendee.line-icon name="wifi" /> 100% Offline Ready</span>
                        <span class="hero-trust-bar__sep">&middot;</span>
                        <span><x-attendee.line-icon name="zap" /> Zero Passwords</span>
                    </div>
                </div>`;

welcome = welcome.replace(oldHeroActions, newHeroActions);

// Replace welcome footer with unified partial
const oldWelcomeFooter = `        {{-- Shown by native-bridge.js, in the store app only: event staff sign in to the panel there. --}}
        <p class="footer-note landing-footer" data-app-only hidden>Event staff: <a href="{{ route('manager.login') }}">Event manager sign in</a> &middot; <a href="{{ route('login') }}">Admin sign in</a></p>
        <p class="footer-note landing-footer" style="display:flex;flex-wrap:wrap;gap:4px 14px;justify-content:center"><a href="{{ route('about') }}">About CampBuddy</a> <a href="{{ route('guide') }}">WordCamp 101</a> <a href="{{ route('brand-guidelines') }}">Brand Guidelines</a> <a href="{{ route('privacy') }}">Privacy notice</a> <a href="{{ route('terms') }}">Terms of use</a> <a href="mailto:{{ config('campbuddy.legal.grievance_email') }}?subject=CampBuddy%20data%20request">Data request or complaint</a> @include('attendee.partials.apk-link')</p>
        <div style="padding: 0 16px;">
            @include('attendee.partials.footer-social')
        </div>
        <footer class="footer-note landing-footer">&copy; {{ date('Y') }} {{ config('campbuddy.name') }} &middot; Built by <a href="https://wpsimplified.in" target="_blank" rel="noopener" style="font-weight:600;color:inherit;text-decoration:underline">WPSimplified</a> for the WordPress community.</footer>`;

const newWelcomeFooter = `        @include('attendee.partials.footer')`;

welcome = welcome.replace(oldWelcomeFooter, newWelcomeFooter);
fs.writeFileSync('resources/views/welcome.blade.php', welcome, 'utf8');
console.log('Updated welcome.blade.php');

// =========================================================================
// 2. UPDATE guide-general.blade.php
// =========================================================================
let guideGen = fs.readFileSync('resources/views/attendee/guide-general.blade.php', 'utf8');

const oldGuideGenTopbar = `        <header class="topbar">
            <a href="{{ route('home') }}" class="brand" data-track="home_link_click" data-track-target="brand">
                <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
            </a>
            <div class="topbar__actions">
        <button type="button" id="open-on-phone-btn" class="topbar__text-btn" hidden>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="7" y="2" width="10" height="20" rx="2" />
                <path d="M11 18h2" />
            </svg>
            <span>Open on phone</span>
        </button>

                <a href="{{ route('home') }}" class="topbar__text-btn" data-track="home_link_click" data-track-target="wordcamps">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    <span>All WordCamps</span>
                </a>
            </div>
        </header>`;

const newGuideGenTopbar = `        <header class="topbar">
            <div class="topbar__inner">
                <a href="{{ route('home') }}" class="brand" data-track="home_link_click" data-track-target="brand">
                    <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
                </a>
                <div class="topbar__actions">
                    <button type="button" id="open-on-phone-btn" class="topbar__text-btn" hidden>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="7" y="2" width="10" height="20" rx="2" />
                            <path d="M11 18h2" />
                        </svg>
                        <span>Open on phone</span>
                    </button>

                    <a href="{{ route('home') }}" class="topbar__text-btn" data-track="home_link_click" data-track-target="wordcamps">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                        <span>All WordCamps</span>
                    </a>
                </div>
            </div>
        </header>`;

guideGen = guideGen.replace(oldGuideGenTopbar, newGuideGenTopbar);

const oldGuideGenFooter = `        <p class="footer-note landing-footer" style="display:flex;flex-wrap:wrap;gap:4px 14px;justify-content:center"><a href="{{ route('about') }}">About CampBuddy</a> <a href="{{ route('guide') }}">WordCamp 101</a> <a href="{{ route('brand-guidelines') }}">Brand Guidelines</a> <a href="{{ route('privacy') }}">Privacy notice</a> <a href="{{ route('terms') }}">Terms of use</a> <a href="mailto:{{ config('campbuddy.legal.grievance_email') }}?subject=CampBuddy%20data%20request">Data request or complaint</a> @include('attendee.partials.apk-link')</p>
        <div style="padding: 0 16px;">
            @include('attendee.partials.footer-social')
        </div>
        <footer class="footer-note landing-footer">&copy; {{ date('Y') }} {{ config('campbuddy.name') }} &middot; Built by <a href="https://wpsimplified.in" target="_blank" rel="noopener" style="font-weight:600;color:inherit;text-decoration:underline">WPSimplified</a> for the WordPress community.</footer>`;

const newGuideGenFooter = `        @include('attendee.partials.footer')`;

guideGen = guideGen.replace(oldGuideGenFooter, newGuideGenFooter);
fs.writeFileSync('resources/views/attendee/guide-general.blade.php', guideGen, 'utf8');
console.log('Updated guide-general.blade.php');

// =========================================================================
// 3. UPDATE about.blade.php
// =========================================================================
let about = fs.readFileSync('resources/views/attendee/about.blade.php', 'utf8');

const oldAboutTopbar = `        <header class="topbar">
            <a href="{{ route('home') }}" class="brand" data-track="home_link_click" data-track-target="brand">
                <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
            </a>
            <div class="topbar__actions">
                <button type="button" id="open-on-phone-btn" class="topbar__text-btn" hidden>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="7" y="2" width="10" height="20" rx="2" />
                        <path d="M11 18h2" />
                    </svg>
                    <span>Open on phone</span>
                </button>
                <a href="{{ route('home') }}" class="topbar__text-btn" data-track="home_link_click" data-track-target="wordcamps">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    <span>All WordCamps</span>
                </a>
            </div>
        </header>`;

const newAboutTopbar = `        <header class="topbar">
            <div class="topbar__inner">
                <a href="{{ route('home') }}" class="brand" data-track="home_link_click" data-track-target="brand">
                    <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
                </a>
                <div class="topbar__actions">
                    <button type="button" id="open-on-phone-btn" class="topbar__text-btn" hidden>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="7" y="2" width="10" height="20" rx="2" />
                            <path d="M11 18h2" />
                        </svg>
                        <span>Open on phone</span>
                    </button>
                    <a href="{{ route('home') }}" class="topbar__text-btn" data-track="home_link_click" data-track-target="wordcamps">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                        <span>All WordCamps</span>
                    </a>
                </div>
            </div>
        </header>`;

about = about.replace(oldAboutTopbar, newAboutTopbar);

const oldAboutFooter = `        <p class="footer-note landing-footer" style="display:flex;flex-wrap:wrap;gap:4px 14px;justify-content:center">
            <a href="{{ route('about') }}">About CampBuddy</a>
            <a href="{{ route('guide') }}">WordCamp 101</a>
            <a href="{{ route('brand-guidelines') }}">Brand Guidelines</a>
            <a href="{{ route('privacy') }}">Privacy notice</a>
            <a href="{{ route('terms') }}">Terms of use</a>
            <a href="mailto:{{ config('campbuddy.legal.grievance_email') }}?subject=CampBuddy%20inquiry">Contact us</a>
            @include('attendee.partials.apk-link')
        </p>
        <div style="padding: 0 16px;">
            @include('attendee.partials.footer-social')
        </div>
        <footer class="footer-note landing-footer">&copy; {{ date('Y') }} {{ config('campbuddy.name') }} &middot; Built by <a href="https://wpsimplified.in" target="_blank" rel="noopener" style="font-weight:600;color:inherit;text-decoration:underline">WPSimplified</a> for the WordPress community.</footer>`;

const newAboutFooter = `        @include('attendee.partials.footer')`;

about = about.replace(oldAboutFooter, newAboutFooter);
fs.writeFileSync('resources/views/attendee/about.blade.php', about, 'utf8');
console.log('Updated about.blade.php');

// =========================================================================
// 4. UPDATE legal/layout.blade.php
// =========================================================================
let legalLayout = fs.readFileSync('resources/views/attendee/legal/layout.blade.php', 'utf8');

const oldLegalTopbar = `        <header class="topbar">
            <a href="{{ route('home') }}" class="brand">
                <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
            </a>
            <div class="topbar__actions">
                <a href="{{ route('home') }}" class="topbar__text-btn">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                    <span>All WordCamps</span>
                </a>
            </div>
        </header>`;

const newLegalTopbar = `        <header class="topbar">
            <div class="topbar__inner">
                <a href="{{ route('home') }}" class="brand">
                    <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
                </a>
                <div class="topbar__actions">
                    <a href="{{ route('home') }}" class="topbar__text-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
                        <span>All WordCamps</span>
                    </a>
                </div>
            </div>
        </header>`;

legalLayout = legalLayout.replace(oldLegalTopbar, newLegalTopbar);

const oldLegalFooter = `                <footer class="legal-footer">
                    <div class="legal-footer-links" style="display:flex;flex-wrap:wrap;gap:4px 14px;justify-content:center">
                        <a href="{{ route('about') }}">About CampBuddy</a>
                        <a href="{{ route('guide') }}">WordCamp 101</a>
                        <a href="{{ route('brand-guidelines') }}">Brand Guidelines</a>
                        <a href="{{ route('privacy') }}">Privacy notice</a>
                        <a href="{{ route('terms') }}">Terms of use</a>
                        <a href="mailto:{{ config('campbuddy.legal.grievance_email') }}?subject=CampBuddy%20data%20request">Data request or complaint</a>
                        @include('attendee.partials.apk-link')
                    </div>
                    <div style="padding: 10px 0;">
                        @include('attendee.partials.footer-social')
                    </div>
                    <div class="legal-footer-copy">
                        &copy; {{ date('Y') }} {{ config('campbuddy.name') }} &middot; Built by <a href="https://wpsimplified.in" target="_blank" rel="noopener" style="font-weight:600;color:inherit;text-decoration:underline">WPSimplified</a> for the WordPress community.
                    </div>
                </footer>`;

const newLegalFooter = ``; // Footer moved outside legal-shell so it spans matching container

legalLayout = legalLayout.replace(oldLegalFooter, newLegalFooter);

const oldLegalMainClose = `            </div>
        </main>
    </div>`;

const newLegalMainClose = `            </div>
        </main>

        @include('attendee.partials.footer')
    </div>`;

legalLayout = legalLayout.replace(oldLegalMainClose, newLegalMainClose);
fs.writeFileSync('resources/views/attendee/legal/layout.blade.php', legalLayout, 'utf8');
console.log('Updated legal/layout.blade.php');
