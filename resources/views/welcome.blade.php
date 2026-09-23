<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'CampBuddy') }} — Your WordCamp companion</title>
    <meta name="description" content="CampBuddy is the mobile-first companion app for WordCamp attendees — guidance on what to do next, session planning, Contributor Day matching, and a digital Camp Card, all local-first and privacy-respecting.">
    <link rel="icon" href="/media/favicon.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --maroon: #5a1620;
            --maroon-dark: #3a0e16;
            --maroon-soft: #7a2230;
            --cream: #fbefdc;
            --cream-soft: #fff8ee;
            --amber: #d97d05;
            --amber-light: #f2a93b;
            --ink: #2b1712;
            --muted: #7a6a60;
            --line: #ecdfcd;
            --radius: 18px;
            --shadow: 0 20px 45px -20px rgba(58, 14, 22, 0.35);
        }

        * { box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            margin: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--ink);
            background: var(--cream-soft);
            -webkit-font-smoothing: antialiased;
        }

        img { max-width: 100%; display: block; }

        a { color: inherit; }

        .container {
            width: 100%;
            max-width: 1080px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Header */
        .site-header {
            padding: 22px 0;
        }

        .site-header .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.15rem;
            color: var(--maroon);
        }

        .brand img {
            height: 40px;
            width: 40px;
            border-radius: 11px;
        }

        .site-header nav {
            display: flex;
            gap: 28px;
        }

        .site-header nav a {
            text-decoration: none;
            font-weight: 600;
            font-size: 0.95rem;
            color: var(--ink);
            opacity: 0.75;
            transition: opacity 0.15s ease;
        }

        .site-header nav a:hover { opacity: 1; }

        /* Hero */
        .hero {
            background: radial-gradient(120% 140% at 15% 0%, var(--maroon-soft) 0%, var(--maroon) 45%, var(--maroon-dark) 100%);
            color: var(--cream);
            border-radius: 0 0 40px 40px;
            padding: 64px 0 80px;
            position: relative;
            overflow: hidden;
        }

        .hero::after {
            content: "";
            position: absolute;
            right: -120px;
            top: -120px;
            width: 380px;
            height: 380px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(242, 169, 59, 0.35) 0%, rgba(242, 169, 59, 0) 70%);
        }

        .hero .container {
            position: relative;
            z-index: 1;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(251, 239, 220, 0.14);
            border: 1px solid rgba(251, 239, 220, 0.28);
            padding: 7px 16px;
            border-radius: 999px;
            font-size: 0.82rem;
            font-weight: 600;
            letter-spacing: 0.02em;
        }

        .eyebrow .dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--amber-light);
        }

        .hero h1 {
            font-size: clamp(2.1rem, 4.6vw, 3.4rem);
            line-height: 1.12;
            margin: 22px 0 18px;
            max-width: 15ch;
            font-weight: 800;
            letter-spacing: -0.01em;
        }

        .hero h1 span {
            color: var(--amber-light);
        }

        .hero p.lede {
            font-size: 1.1rem;
            line-height: 1.6;
            max-width: 46ch;
            color: rgba(251, 239, 220, 0.86);
            margin: 0 0 32px;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 13px 26px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 0.98rem;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }

        .btn:hover { transform: translateY(-1px); }

        .btn-primary {
            background: var(--amber);
            color: var(--cream-soft);
            box-shadow: 0 14px 30px -12px rgba(217, 125, 5, 0.65);
        }

        .btn-primary:hover { background: var(--amber-light); }

        .btn-ghost {
            background: rgba(251, 239, 220, 0.08);
            border-color: rgba(251, 239, 220, 0.35);
            color: var(--cream);
        }

        .btn-ghost:hover { background: rgba(251, 239, 220, 0.16); }

        /* Sections */
        section { padding: 76px 0; }

        .section-head {
            max-width: 620px;
            margin: 0 auto 44px;
            text-align: center;
        }

        .section-head .kicker {
            color: var(--amber);
            font-weight: 700;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .section-head h2 {
            font-size: clamp(1.6rem, 3vw, 2.1rem);
            margin: 10px 0 12px;
            font-weight: 800;
            color: var(--maroon);
        }

        .section-head p {
            color: var(--muted);
            font-size: 1.02rem;
            line-height: 1.6;
            margin: 0;
        }

        /* Philosophy pillars */
        .pillars {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .pillar {
            background: var(--cream);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 28px 26px;
        }

        .pillar .icon {
            width: 46px;
            height: 46px;
            border-radius: 12px;
            background: var(--maroon);
            color: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            margin-bottom: 16px;
        }

        .pillar h3 {
            margin: 0 0 8px;
            font-size: 1.08rem;
            font-weight: 700;
            color: var(--ink);
        }

        .pillar p {
            margin: 0;
            color: var(--muted);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Feature grid */
        .features-section { background: var(--cream); }

        .feature-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .feature-card {
            background: var(--cream-soft);
            border-radius: var(--radius);
            padding: 26px;
            border: 1px solid var(--line);
            transition: box-shadow 0.15s ease, transform 0.15s ease;
        }

        .feature-card:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .feature-card .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--amber), var(--amber-light));
            color: var(--cream-soft);
            font-weight: 800;
            font-size: 1.05rem;
            margin-bottom: 14px;
        }

        .feature-card h3 {
            margin: 0 0 8px;
            font-size: 1.05rem;
            font-weight: 700;
        }

        .feature-card p {
            margin: 0;
            color: var(--muted);
            font-size: 0.93rem;
            line-height: 1.6;
        }

        /* How it works */
        .steps {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            counter-reset: step;
        }

        .step {
            position: relative;
            padding: 24px 20px 20px;
            border-radius: var(--radius);
            background: var(--cream);
            border: 1px solid var(--line);
        }

        .step .num {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: var(--maroon);
            color: var(--cream);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.95rem;
            margin-bottom: 14px;
        }

        .step h3 {
            margin: 0 0 6px;
            font-size: 1rem;
            font-weight: 700;
        }

        .step p {
            margin: 0;
            color: var(--muted);
            font-size: 0.9rem;
            line-height: 1.55;
        }

        /* Organizers band */
        .organizers {
            background: var(--maroon);
            color: var(--cream);
            border-radius: var(--radius);
            padding: 44px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 32px;
            flex-wrap: wrap;
        }

        .organizers .copy { max-width: 480px; }

        .organizers .kicker {
            color: var(--amber-light);
            font-weight: 700;
            font-size: 0.82rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .organizers h2 {
            font-size: 1.5rem;
            margin: 10px 0 12px;
            font-weight: 800;
        }

        .organizers p {
            margin: 0;
            color: rgba(251, 239, 220, 0.82);
            line-height: 1.6;
        }

        /* Event picker */
        .picker-card {
            background: var(--cream);
            border: 1px solid var(--line);
            border-radius: var(--radius);
            padding: 40px;
            text-align: center;
            max-width: 620px;
            margin: 0 auto;
        }

        .picker-card .icon-circle {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--amber);
            color: var(--cream-soft);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            font-weight: 800;
            margin: 0 auto 18px;
        }

        .picker-card h2 {
            margin: 0 0 10px;
            font-size: 1.3rem;
            color: var(--maroon);
            font-weight: 800;
        }

        .picker-card p.empty-copy {
            color: var(--muted);
            line-height: 1.6;
            margin: 0;
        }

        .event-list {
            list-style: none;
            margin: 24px 0 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .event-list a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            text-decoration: none;
            background: var(--cream-soft);
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 16px 20px;
            font-weight: 700;
            color: var(--ink);
            transition: border-color 0.15s ease, transform 0.15s ease;
        }

        .event-list a:hover {
            border-color: var(--amber);
            transform: translateY(-1px);
        }

        .event-list a .arrow {
            color: var(--amber);
            font-weight: 800;
        }

        /* Footer */
        .site-footer {
            padding: 40px 0 48px;
            border-top: 1px solid var(--line);
        }

        .site-footer .container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
        }

        .site-footer .brand { font-size: 1rem; }
        .site-footer .brand img { height: 30px; width: 30px; }

        .site-footer small {
            color: var(--muted);
            font-size: 0.85rem;
        }

        @media (max-width: 860px) {
            .pillars, .feature-grid { grid-template-columns: repeat(2, 1fr); }
            .steps { grid-template-columns: repeat(2, 1fr); }
            .organizers { flex-direction: column; align-items: flex-start; }
        }

        @media (max-width: 600px) {
            .site-header nav { display: none; }
            .pillars, .feature-grid, .steps { grid-template-columns: 1fr; }
            .hero { padding: 48px 0 64px; border-radius: 0 0 28px 28px; }
            section { padding: 56px 0; }
            .organizers { padding: 32px 24px; }
            .picker-card { padding: 28px 22px; }
        }
    </style>
</head>
<body>

    <header class="site-header">
        <div class="container">
            <a href="/" class="brand">
                <img src="/media/logo.png" alt="{{ config('app.name', 'CampBuddy') }}">
                {{ config('app.name', 'CampBuddy') }}
            </a>
            <nav>
                <a href="#about">What it is</a>
                <a href="#how-it-works">How it works</a>
                <a href="#organizers">For organizers</a>
                <a href="#find-your-camp">Find your camp</a>
            </nav>
        </div>
    </header>

    <section class="hero">
        <div class="container">
            <span class="eyebrow"><span class="dot"></span> Built for WordCamp attendees</span>
            <h1>The one question CampBuddy answers: <span>"what should I do now?"</span></h1>
            <p class="lede">
                CampBuddy is a mobile-first, local-first companion app for WordCamp attendees.
                It turns a busy schedule, a venue full of strangers, and a list of sponsors into
                simple, timely guidance — so every attendee, especially first-timers, always
                knows their next move.
            </p>
            <div class="hero-actions">
                <a href="#find-your-camp" class="btn btn-primary">Find your WordCamp →</a>
                <a href="#about" class="btn btn-ghost">See what it does</a>
            </div>
        </div>
    </section>

    <section id="about">
        <div class="container">
            <div class="section-head">
                <span class="kicker">What it is</span>
                <h2>Not a schedule app. A guide.</h2>
                <p>A schedule is just data. CampBuddy's job is to turn everything happening around a WordCamp into a simple next step — for the first-timer standing in the lobby unsure where to go, and the returning attendee who just wants their day organised.</p>
            </div>
            <div class="pillars">
                <div class="pillar">
                    <div class="icon">→</div>
                    <h3>Guidance over information</h3>
                    <p>Instead of just showing "Contributor Day — 9:00 AM," CampBuddy tells you what to actually do about it — and when.</p>
                </div>
                <div class="pillar">
                    <div class="icon">◐</div>
                    <h3>Local by default</h3>
                    <p>Your data lives on your device unless you explicitly choose to share it. Nothing is public by accident, and everything shareable stays revocable.</p>
                </div>
                <div class="pillar">
                    <div class="icon">✓</div>
                    <h3>No accounts, ever</h3>
                    <p>Open it and go. No sign-up, no password, no email required — CampBuddy works the moment you land on your event.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="features-section">
        <div class="container">
            <div class="section-head">
                <span class="kicker">Inside the app</span>
                <h2>Everything an attendee needs, one tab away</h2>
                <p>A handful of focused tools, designed to work one-handed on a phone in a crowded hallway.</p>
            </div>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="badge">1</div>
                    <h3>Home</h3>
                    <p>Happening now, up next, and a gentle suggested action — always contextual, never a static dashboard.</p>
                </div>
                <div class="feature-card">
                    <div class="badge">2</div>
                    <h3>My Day</h3>
                    <p>Bookmark sessions, spot schedule clashes before they happen, and get a nudge when a saved session is starting soon.</p>
                </div>
                <div class="feature-card">
                    <div class="badge">3</div>
                    <h3>Quest</h3>
                    <p>Small, achievable prompts that nudge you to explore, meet people, and get the most out of the event — no points, no leaderboards.</p>
                </div>
                <div class="feature-card">
                    <div class="badge">4</div>
                    <h3>Contribute</h3>
                    <p>New to contributing to WordPress? Answer a few questions and get matched to the contributor team that fits you best.</p>
                </div>
                <div class="feature-card">
                    <div class="badge">5</div>
                    <h3>Explore</h3>
                    <p>Find people worth meeting through opt-in interest matching, browse sponsor booths and deals, and get event info in one place.</p>
                </div>
                <div class="feature-card">
                    <div class="badge">6</div>
                    <h3>Camp Card</h3>
                    <p>A portable digital identity with a QR code — share only the details you choose, on your own terms.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works">
        <div class="container">
            <div class="section-head">
                <span class="kicker">How it works</span>
                <h2>From landing page to your day, in under two minutes</h2>
            </div>
            <div class="steps">
                <div class="step">
                    <div class="num">1</div>
                    <h3>Pick your WordCamp</h3>
                    <p>CampBuddy takes on that event's own name and colors, so it feels like the event's own app.</p>
                </div>
                <div class="step">
                    <div class="num">2</div>
                    <h3>Tell it a little about you</h3>
                    <p>A short, skippable onboarding: your interests, whether it's your first WordCamp, and what you're hoping to get out of it.</p>
                </div>
                <div class="step">
                    <div class="num">3</div>
                    <h3>Get guided through the day</h3>
                    <p>Home tells you what's happening now and what's worth doing next, based on the real schedule.</p>
                </div>
                <div class="step">
                    <div class="num">4</div>
                    <h3>Save, connect, contribute</h3>
                    <p>Bookmark sessions, build your Camp Card, and — when you're ready — opt in to meeting other attendees.</p>
                </div>
            </div>
        </div>
    </section>

    <section id="organizers">
        <div class="container">
            <div class="organizers">
                <div class="copy">
                    <span class="kicker">For organizers</span>
                    <h2>Hand your attendees your event's own companion app</h2>
                    <p>Set up your WordCamp's branding, schedule, speakers, sponsors, and Quest prompts from a simple admin panel — no code required. CampBuddy adopts your event's name and colors automatically.</p>
                </div>
                <a href="#find-your-camp" class="btn btn-primary">Find your event →</a>
            </div>
        </div>
    </section>

    <section id="find-your-camp">
        <div class="container">
            @if (($events ?? collect())->isEmpty())
                <div class="picker-card">
                    <div class="icon-circle">i</div>
                    <h2>No WordCamp is live yet</h2>
                    <p class="empty-copy">CampBuddy isn't attached to an active event right now — check back closer to the event, or ask your organizer if CampBuddy is planned for your WordCamp.</p>
                </div>
            @else
                <div class="picker-card">
                    <div class="icon-circle">→</div>
                    <h2>Choose your WordCamp</h2>
                    <p class="empty-copy">Select your event to open its companion app.</p>
                    <ul class="event-list">
                        @foreach ($events as $event)
                            <li>
                                <a href="{{ route('event.home', $event) }}">
                                    {{ $event->display_name }}
                                    <span class="arrow">→</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    </section>

    <footer class="site-footer">
        <div class="container">
            <a href="/" class="brand">
                <img src="/media/logo.png" alt="{{ config('app.name', 'CampBuddy') }}">
                {{ config('app.name', 'CampBuddy') }}
            </a>
            <small>&copy; {{ date('Y') }} {{ config('app.name', 'CampBuddy') }}. Made for the WordPress community.</small>
        </div>
    </footer>

</body>
</html>
