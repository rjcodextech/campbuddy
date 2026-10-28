{{-- /brand-guidelines — the people-facing CampBuddy brand guidelines
(CAMPBUDDY-BRAND-GUIDELINES.md, docs spec/26), drawn with the live tokens:
every swatch, font, radius, shadow, wave and button below is the real one.
Same shell as /guide: no event, no tab bar. Data: App\Support\BrandTokens. --}}
@php
    use App\Support\BrandTokens as B;
    use App\Support\LineIcons;
    $title = 'Brand guidelines | '.config('campbuddy.name');
    $description = 'How CampBuddy looks, sounds and moves: logo, colour, type, shape, motif, voice, icons, social posts and reels.';
    $sections = [
        'brand' => 'Brand', 'logo' => 'Logo', 'colour' => 'Colour', 'type' => 'Typography', 'shape' => 'Space & shape',
        'motif' => 'Motif & motion', 'voice' => 'Voice & tone', 'ui' => 'Icons & UI', 'social' => 'Social posts',
        'reels' => 'Reels', 'apps' => 'Apps', 'checklist' => 'Checklist',
    ];
    $n = fn (string $key) => str_pad((string) (array_search($key, array_keys($sections), true) + 1), 2, '0', STR_PAD_LEFT);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title }}</title>
    @include('attendee.partials.seo', ['seoTitle' => $title, 'seoDescription' => $description])
    @include('attendee.partials.head-meta', ['manifestUrl' => route('manifest', absolute: false)])
    @include('attendee.partials.analytics')
    @vite(['resources/css/fonts.css', 'resources/scss/main.scss', 'resources/js/attendee/app.js'])
</head>
<body class="app-shell" data-page-type="brand_guidelines">
    @include('attendee.partials.line-icons')
    <div class="app-frame">
        @include('attendee.partials.public-header')

        <main id="main-content" class="bg-page" tabindex="-1">
            <section class="landing-hero bg-hero">
                <div class="landing-hero__text">
                    <p class="home-hero__eyebrow bg-kicker">Design system · Campfire Daylight</p>
                    <h1 class="landing-hero__title">Brand guidelines</h1>
                    <p class="landing-hero__lead">Your WordCamp companion. How CampBuddy looks, sounds and moves — logo, colour, type, motif, voice, social posts and reels. Every sample on this page is the live token.</p>
                    <nav class="bg-chips" aria-label="Sections">
                        @foreach ($sections as $id => $label)
                            <a href="#{{ $id }}" class="bg-chip">{{ $label }}</a>
                        @endforeach
                    </nav>
                </div>
            </section>

            {{-- 01 Brand --}}
            <section id="brand" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('brand') }} · Who we are</p>
                <h2 class="section-head__title bg-h2">A friend who has been to WordCamp before.</h2>
                <p class="bg-lead">CampBuddy is a free app for WordCamp attendees, volunteers, speakers and organisers — schedule, My Day, people, Camp Card, quests and deals, all working offline. Made by WPSimplified, mostly for people in India, mostly on phones, many at their first WordCamp.</p>
                <div class="bg-grid bg-grid--5">
                    @foreach ([['calendar', 'My Day', 'Your talks, with reminders.'], ['users', 'People', 'Find who shares your interests.'], ['id-card', 'Camp Card', 'Your badge, as a QR.'], ['star', 'Quests', 'Small things worth doing.'], ['ticket', 'Deals', 'Sponsor offers for you.']] as [$icon, $name, $text])
                        <div class="card bg-pillar">
                            <span class="bg-pillar__icon"><x-attendee.line-icon :name="$icon" /></span>
                            <strong>{{ $name }}</strong>
                            <span>{{ $text }}</span>
                        </div>
                    @endforeach
                </div>
                <blockquote class="bg-promise">
                    <span class="bg-kicker">Brand promise</span>
                    Calm and useful on a busy day. Every screen answers “what do I do next?” in one glance.
                </blockquote>
            </section>

            {{-- 02 Logo --}}
            <section id="logo" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('logo') }} · Logo</p>
                <h2 class="section-head__title bg-h2">One logo, three grounds.</h2>
                <p class="bg-lead">The campfire mark with the CampBuddy wordmark. Use the supplied files — never redraw, retype or recolour it. There is no white wordmark: on dark it always sits on a paper plate.</p>
                <div class="bg-grid bg-grid--3">
                    <figure class="bg-logo bg-logo--paper"><img src="/media/logo-wordmark.png" alt="" width="701" height="208"><figcaption><strong>On paper</strong> Default: header, brand bar.</figcaption></figure>
                    <figure class="bg-logo bg-logo--ink"><span class="bg-plate"><img src="/media/logo-wordmark.png" alt="" width="701" height="208"></span><figcaption><strong>On ink</strong> On a paper plate, centred.</figcaption></figure>
                    <figure class="bg-logo bg-logo--brand"><span class="bg-plate"><img src="/media/logo-wordmark.png" alt="" width="701" height="208"></span><figcaption><strong>On the gradient</strong> Same plate, maroon → wine.</figcaption></figure>
                </div>
                <div class="bg-split">
                    <figure class="bg-clear"><span><img src="/media/logo-wordmark.png" alt="" width="701" height="208"></span><figcaption>Clear space: ≈ 28% of the logo height on every side.</figcaption></figure>
                    <dl class="bg-specs">
                        <dt>Minimum</dt><dd>120 px wide</dd>
                        <dt>Header</dt><dd>32 px tall</dd>
                        <dt>App icon</dt><dd>Wine tile, cream circle (favicon.png)</dd>
                        <dt>Files</dt><dd><code>logo-wordmark.png</code> · <code>logo.png</code> · <code>favicon.png</code></dd>
                    </dl>
                </div>
                <h3 class="bg-h3">Never change the logo</h3>
                <div class="bg-grid bg-grid--3">
                    <figure class="bg-dont"><span><img src="/media/logo-wordmark.png" alt="" style="transform:scaleX(1.35)"></span><figcaption>Don’t stretch or squash</figcaption></figure>
                    <figure class="bg-dont"><span><img src="/media/logo-wordmark.png" alt="" style="transform:rotate(-12deg)"></span><figcaption>Don’t rotate</figcaption></figure>
                    <figure class="bg-dont"><span><img src="/media/logo-wordmark.png" alt="" style="filter:hue-rotate(160deg)"></span><figcaption>Don’t recolour</figcaption></figure>
                    <figure class="bg-dont"><span><img src="/media/logo-wordmark.png" alt="" style="filter:drop-shadow(0 6px 6px rgba(0,0,0,.5))"></span><figcaption>Don’t add shadows or glows</figcaption></figure>
                    <figure class="bg-dont bg-dont--ink"><span><img src="/media/logo-wordmark.png" alt=""></span><figcaption>Don’t put it straight on dark</figcaption></figure>
                    <figure class="bg-dont bg-dont--busy"><span><img src="/media/logo-wordmark.png" alt=""></span><figcaption>Don’t place it on a busy image</figcaption></figure>
                </div>
            </section>

            {{-- 03 Colour --}}
            <section id="colour" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('colour') }} · Colour</p>
                <h2 class="section-head__title bg-h2">Warm paper, ink and one maroon voice.</h2>
                <p class="bg-lead">Paper ground, ink text, maroon for the brand. The logo’s wine is its gradient partner; ochre is light — glows, waves, highlights on ink — never text on paper. One fixed palette for every event.</p>
                <div class="bg-share" role="img" aria-label="Share of a screen">
                    @foreach (B::SHARE as [$name, $pct, $bg, $fg])
                        {{-- A slice too thin for its name shows only the colour; the key below names every slice. --}}
                        <span @class(['bg-share__thin' => $pct < 15]) style="flex: {{ $pct }}; background: {{ $bg }}; color: {{ $fg }}">{{ $name }} {{ $pct }}%</span>
                    @endforeach
                </div>
                <ul class="bg-share-key">
                    @foreach (B::SHARE as [$name, $pct, $bg])
                        <li><span style="background: {{ $bg }}"></span>{{ $name }} {{ $pct }}%</li>
                    @endforeach
                </ul>
                @foreach (B::COLOURS as $group => $swatches)
                    <h3 class="bg-h3">{{ $group }}</h3>
                    <div class="bg-grid bg-grid--4">
                        @foreach ($swatches as [$token, $hex, $use])
                            <div class="bg-swatch">
                                <span class="bg-swatch__chip" style="background: {{ $hex }}"></span>
                                <code>{{ $token }}</code>
                                <span class="bg-swatch__hex">{{ $hex }}</span>
                                <span class="bg-swatch__use">{{ $use }}</span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
                <h3 class="bg-h3">Contrast — 4.5:1 for text, 3:1 for big text and icons</h3>
                <div class="bg-grid bg-grid--3">
                    @foreach (B::CONTRAST as [$label, $fg, $bgc])
                        @php($ratio = B::contrast($fg, $bgc))
                        <div class="bg-contrast" style="background: {{ $bgc }}; color: {{ $fg }}">
                            <strong>Aa WordCamp</strong>
                            <span class="bg-contrast__tag">{{ $label }} · {{ number_format($ratio, 1) }}:1</span>
                            <span class="bg-contrast__mark {{ $ratio >= 4.5 ? 'is-ok' : 'is-bad' }}">@if ($ratio >= 4.5)<svg class="line-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>@else<x-attendee.line-icon name="x" />@endif</span>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- 04 Typography --}}
            <section id="type" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('type') }} · Typography</p>
                <h2 class="section-head__title bg-h2">Friendly display, clear text.</h2>
                <p class="bg-lead">Baloo 2 for headings and numbers, Poppins for everything else. Both self-hosted, both with Devanagari for Hindi.</p>
                <div class="bg-grid bg-grid--2">
                    <div class="card bg-font"><span class="bg-font__aa" style="font-family: var(--font-display)">Aa</span><strong>Baloo 2</strong><span>Headings, numbers, display · 600 · 700 · 800</span><span class="bg-font__set" style="font-family: var(--font-display)">ABCDEFGHIJKLM abcdefghijklm 0123456789 पहला वर्डकैंप</span></div>
                    <div class="card bg-font"><span class="bg-font__aa" style="font-family: Poppins">Aa</span><strong>Poppins</strong><span>Body, UI, labels, buttons · 400 · 500 · 600 · 700</span><span class="bg-font__set">ABCDEFGHIJKLM abcdefghijklm 0123456789 पहला वर्डकैंप</span></div>
                </div>
                <h3 class="bg-h3">Type scale</h3>
                <div class="card bg-scale">
                    @foreach (B::TYPE_SCALE as [$role, $family, $weight, $size, $sample])
                        <div class="bg-scale__row">
                            <div class="bg-scale__meta"><strong>{{ $role }}</strong><span>{{ $family === 'display' ? 'Baloo 2' : 'Poppins' }} {{ $weight }} · {{ $size }} px</span></div>
                            <div class="bg-scale__sample" style="font-family: {{ $family === 'display' ? 'var(--font-display)' : 'Poppins' }}; font-weight: {{ $weight }}; font-size: {{ $size }}px;{{ in_array($role, ['Kicker', 'Badge']) ? ' letter-spacing:.14em; color: var(--brand-primary)' : '' }}">{{ $sample }}</div>
                        </div>
                    @endforeach
                </div>
                <ul class="bg-rules">
                    <li>Sentence case for headings; uppercase only for kickers and badges.</li>
                    <li>Highlight <strong>one</strong> phrase per headline: maroon on paper, ochre on ink.</li>
                    <li>12 px is the smallest text anywhere. Lines up to about 66 characters.</li>
                    <li>Camp Card keeps its own serif and mono — it is a printed badge, not app UI.</li>
                </ul>
            </section>

            {{-- 05 Space & shape --}}
            <section id="shape" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('shape') }} · Space & shape</p>
                <h2 class="section-head__title bg-h2">A 4 px rhythm, corners that grow with the surface.</h2>
                <h3 class="bg-h3">Spacing</h3>
                <div class="card bg-space">
                    @foreach (B::SPACE as [$name, $px])
                        <div class="bg-space__row"><code>{{ $px }}</code><span style="width: {{ $px * 2 }}px"></span></div>
                    @endforeach
                </div>
                <h3 class="bg-h3">Radius</h3>
                <div class="bg-grid bg-grid--4">
                    @foreach (B::RADII as [$name, $px])
                        <div class="bg-radius"><span style="border-radius: {{ $px }}px"></span><strong>{{ $name }}</strong> {{ $px === 999 ? 'round' : $px.' px' }}</div>
                    @endforeach
                </div>
                <h3 class="bg-h3">Elevation — ink-tinted, two layers</h3>
                <div class="bg-grid bg-grid--5">
                    @foreach (B::SHADOWS as [$name, $shadow])
                        <div class="bg-shadow" style="box-shadow: {{ $shadow }}"><code>{{ $name }}</code></div>
                    @endforeach
                </div>
            </section>

            {{-- 06 Motif & motion --}}
            <section id="motif" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('motif') }} · Motif & motion</p>
                <h2 class="section-head__title bg-h2">Waves, dots and a warm glow.</h2>
                <p class="bg-lead">Smooth sine waves (never zig-zags), a dot grid and a soft ochre-and-maroon glow. Motion is slow and ambient; reduced motion freezes every loop.</p>
                <div class="bg-grid bg-grid--4">
                    <div class="card bg-motif"><span class="bg-motif__wave"></span><strong>Wave line</strong><span>Kickers, under section titles, section rails.</span></div>
                    <div class="card bg-motif"><span class="bg-motif__dots"></span><strong>Dot grid</strong><span>Hero and post texture, 2 px every 22–26 px.</span></div>
                    <div class="card bg-motif"><span class="bg-motif__glow"></span><strong>Glow</strong><span>Ochre top right, maroon bottom left.</span></div>
                    <div class="card bg-motif"><span class="bg-motif__swell"></span><strong>Swell</strong><span>The soft wave at a hero’s foot.</span></div>
                </div>
                <dl class="bg-specs bg-specs--wide">
                    <dt>Section rail</dt><dd>A drifting wave with a glowing node between sections (9 s loop).</dd>
                    <dt>Buttons</dt><dd>Lift 2 px and a sheen sweeps once on hover (0.8 s); press settles back.</dd>
                    <dt>Cards</dt><dd>Lift 4 px, larger shadow, maroon-tinted border on hover.</dd>
                    <dt>Reveal</dt><dd>Sections below the fold fade up as they scroll in (0.5 s).</dd>
                    <dt>UI</dt><dd>150–250 ms with a standard ease; loops 9–18 s.</dd>
                </dl>
            </section>

            {{-- 07 Voice --}}
            <section id="voice" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('voice') }} · Voice & tone</p>
                <h2 class="section-head__title bg-h2">Plain, friendly, calm, correct.</h2>
                <div class="card bg-voice">
                    @foreach (B::VOICE as [$principle, $say, $avoid])
                        <div class="bg-voice__row"><strong>{{ $principle }}</strong><span class="bg-voice__say"><svg class="line-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg> {{ $say }}</span><span class="bg-voice__avoid"><x-attendee.line-icon name="x" /> {{ $avoid }}</span></div>
                    @endforeach
                </div>
                <p class="bg-note"><strong>Banned words:</strong> {{ implode(' · ', B::BANNED) }}. No emoji as headings, no exclamation-mark spam. In Hindi, WordCamp is masculine — “pehla WordCamp”. Event promos follow the event site’s own tone.</p>
            </section>

            {{-- 08 Icons & UI --}}
            <section id="ui" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('ui') }} · Icons & UI</p>
                <h2 class="section-head__title bg-h2">Line icons and pill buttons.</h2>
                <p class="bg-lead">Line icons on a 24 grid, round caps and joins, in the text colour. No icon fonts, no emoji as icons. Everything tappable is at least 44 × 44 px (48 dp in the app).</p>
                <div class="bg-icons">
                    @foreach (array_keys(LineIcons::ICONS) as $icon)
                        <span title="{{ $icon }}"><x-attendee.line-icon :name="$icon" /><small>{{ $icon }}</small></span>
                    @endforeach
                </div>
                <h3 class="bg-h3">Buttons — hover them</h3>
                <div class="bg-buttons">
                    <button type="button" class="btn btn--primary">Choose your WordCamp</button>
                    <button type="button" class="btn btn--secondary">Read the guide</button>
                    <button type="button" class="btn btn--outline">Explore</button>
                    <button type="button" class="btn btn--outline btn--danger">Delete</button>
                </div>
                <ul class="bg-rules">
                    <li>Focus ring: 2 px maroon outline with a 32% halo — never removed. Hover always has a keyboard twin.</li>
                    <li>Deletes are danger red, never maroon, so “Delete” never looks like “Save”.</li>
                    <li>Badges: a soft tint of the role colour with the role colour as text.</li>
                </ul>
            </section>

            {{-- 09 Social --}}
            <section id="social" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('social') }} · Social posts</p>
                <h2 class="section-head__title bg-h2">Daylight posts and people cards.</h2>
                <p class="bg-lead">Made in Social media (admin, manager and the app). Daylight is the default design; colours come from the event’s own site when they read at 4.5:1, else from CampBuddy’s palette.</p>
                <div class="bg-split">
                    <div class="bg-post">
                        <div class="bg-post__card">
                            <p class="bg-kicker">Free · no sign-up</p>
                            <p class="bg-post__head">Your WordCamp <span>companion</span></p>
                            <p class="bg-post__sub">Free, no sign-up, works offline</p>
                            <div class="bg-post__foot">
                                <span class="bg-post__pill"><img src="/media/logo-wordmark-sm.png" alt="" width="351" height="104"></span>
                                <span class="bg-post__pill"><x-attendee.line-icon name="globe" /> campbuddy.club</span>
                            </div>
                        </div>
                    </div>
                    <div>
                        <dl class="bg-specs">
                            @foreach (B::SOCIAL_SIZES as [$size, $use])
                                <dt>{{ $size }}</dt><dd>{{ $use }}</dd>
                            @endforeach
                        </dl>
                        <ol class="bg-rules">
                            <li>One brand bar at the foot — wordmark pill left, campbuddy.club pill right. Never covered.</li>
                            <li>Headline: Baloo 2 800 (or the event site’s heading font), one highlighted phrase, up to 3 lines; every other line Poppins.</li>
                            <li>Text at least 24 px on a 1080 canvas. Shrink, never cut or split a name.</li>
                            <li>Faces never overlap text. Ochre is never text on paper.</li>
                            <li>Export: PNG at 2×, stamped 600 DPI.</li>
                        </ol>
                    </div>
                </div>
            </section>

            {{-- 10 Reels --}}
            <section id="reels" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('reels') }} · Reels & stories</p>
                <h2 class="section-head__title bg-h2">One vertical master, one safe zone.</h2>
                <div class="bg-split">
                    <figure class="bg-safe" aria-label="Safe zone">
                        <span class="bg-safe__box">Safe area 836 × 1220</span>
                        <span class="bg-safe__top">Keep out: top 250 px</span>
                        <span class="bg-safe__bottom">Keep out: bottom 450 px</span>
                        <span class="bg-safe__rail"></span>
                    </figure>
                    <div>
                        <div class="bg-beats">
                            @foreach (B::REEL_BEATS as [$part, $time, $what, $colour])
                                <div class="bg-beat"><span class="bg-beat__bar" style="background: {{ $colour }}"></span><strong>{{ $part }}</strong> <small>{{ $time }}</small><span>{{ $what }}</span></div>
                            @endforeach
                        </div>
                        <dl class="bg-specs">
                            <dt>Canvas</dt><dd>1080 × 1920 · 9:16 · 30 fps</dd>
                            <dt>Length</dt><dd>20–45 s (60 s max); CampBuddy reels run 30 s</dd>
                            <dt>Safe zone</dt><dd>x 64–900 · y 250–1470 (Instagram + YouTube Shorts)</dd>
                            <dt>Captions</dt><dd>Burned in · Poppins Bold 64–72 px · two lines max</dd>
                            <dt>Logo</dt><dd>Only on the cover and the end card</dd>
                            <dt>Export</dt><dd>MP4 · H.264 · AAC 48 kHz · 8–12 Mbps</dd>
                        </dl>
                    </div>
                </div>
            </section>

            {{-- 11 Apps --}}
            <section id="apps" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('apps') }} · Android & iPhone</p>
                <h2 class="section-head__title bg-h2">The same tokens, native.</h2>
                <ul class="bg-rules">
                    <li>Baloo 2 + Poppins are bundled; the phone’s system font is never used for UI.</li>
                    <li>Cards 20, sheets 28, buttons pills; tap targets 48 dp.</li>
                    <li>The primary button is the maroon → wine gradient with a warm lift; section titles carry the short wave; the event hero has the glow.</li>
                    <li>The server theme (Admin → Mobile app) can only send colours that pass 4.5:1.</li>
                </ul>
            </section>

            {{-- 12 Checklist --}}
            <section id="checklist" class="home-section bg-section">
                <p class="bg-kicker">{{ $n('checklist') }} · Before you ship</p>
                <h2 class="section-head__title bg-h2">The checklist.</h2>
                <ul class="bg-check">
                    <li>Only CampBuddy logo files, on the right ground or a paper plate, centred.</li>
                    <li>Every colour is a token. Contrast passes in every state.</li>
                    <li>Baloo 2 headings, Poppins text, sizes from the scale, one highlighted phrase.</li>
                    <li>Spacing on the 4 px scale; radius matches the surface; tap targets ≥ 44 px.</li>
                    <li>Deletes are danger red, not maroon.</li>
                    <li>Copy is plain, friendly, calm — WordPress, WordCamp, CampBuddy spelled right.</li>
                    <li>Posts and reels follow sections 09 and 10; exports at 2×, 600 DPI.</li>
                    <li><code>npm run brand</code> and the tests pass.</li>
                </ul>
            </section>
        </main>

        @include('attendee.partials.footer')
    </div>
</body>
</html>
