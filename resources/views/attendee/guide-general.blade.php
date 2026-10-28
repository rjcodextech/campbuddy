{{-- /guide — WordCamp 101 before an event is picked. Same shell as the
picker page (welcome.blade.php): no event, so no tab bar. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @php
        $guideTitle = 'First WordCamp? A Beginner\'s Guide to WordCamp | '.config('campbuddy.name');
        $guideDescription = 'New to WordCamp? What happens during the day, the words people use, what to bring, tips for students and how to meet people. A 5-minute read for first-time attendees.';
    @endphp
    <title>{{ $guideTitle }}</title>
    @include('attendee.partials.seo', [
        'seoTitle' => $guideTitle,
        'seoDescription' => $guideDescription,
        'seoSchema' => [
            \App\Support\Seo::guideArticle('Your first WordCamp? Start here.', $guideDescription),
            \App\Support\Seo::faq(array_map(fn ($e) => [$e['q'], $e['a']], \App\Support\FirstTimerGuide::faq())),
            \App\Support\Seo::glossary(),
            \App\Support\Seo::breadcrumbs([['CampBuddy', route('home')], ['WordCamp guide', route('guide')]]),
        ],
    ])

    @include('attendee.partials.head-meta', ['manifestUrl' => route('manifest', absolute: false)])


    @include('attendee.partials.analytics')

    @vite(['resources/css/fonts.css', 'resources/scss/main.scss', 'resources/js/attendee/app.js'])
</head>
<body class="app-shell" data-page-type="guide_general">
    <div class="app-frame">
        @include('attendee.partials.desktop-notice')

        <header class="topbar">
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
        </header>

        <main id="main-content" class="landing-main site-main guide" tabindex="-1">
            @include('attendee.partials.guide-body', ['event' => null])

            {{-- Data Request & Grievance Complaint --}}
            @include('attendee.partials.data-request-section')
        </main>

        @include('attendee.partials.footer')
    </div>

    @include('attendee.templates.shared')
</body>
</html>
