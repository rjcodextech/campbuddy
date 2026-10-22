{{--
    /event/{slug}/memories (EventMemoriesPageController): the photo album and
    the WordPress.tv talks, where the Memories pushes land. A page of its own,
    not the attendee layout: it stays open after the event is archived, when
    the app's other pages (and its tab bar) are gone.
--}}
@php($documentTitle = 'Memories | '.$event->display_name.' | '.config('campbuddy.name'))
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $documentTitle }}</title>
    <meta name="description" content="Photos and recorded talks from {{ $event->display_name }}.">
    <meta name="robots" content="noindex">
    @include('attendee.partials.head-meta', ['manifestUrl' => route('manifest', absolute: false), 'tabIcon' => $event->faviconUrl()])


    @include('attendee.partials.analytics', ['eventSlug' => $event->slug])

    @vite(['resources/css/fonts.css', 'resources/scss/main.scss'])
</head>
<body class="app-shell" data-page-type="memories">
    @include('attendee.partials.line-icons')

    <div class="app-frame">
        <header class="topbar">
            <a href="{{ route('home') }}" class="brand" data-track="home_link_click" data-track-target="brand">
                <img src="/media/logo-wordmark-sm.png" alt="CampBuddy home" class="brand__logo brand__logo--wordmark" width="351" height="104">
            </a>
        </header>

        <main id="main-content" tabindex="-1">
            {{-- The WordCamp site's own artwork, faint behind the name (SiteTheme::hero, components/_hero.scss). --}}
            @php($heroArt = \App\Support\SiteTheme::hero($event))
            <section @class(['home-hero', 'home-hero--art' => $heroArt, 'home-hero--'.($heroArt['kind'] ?? '') => $heroArt, 'home-hero--mask' => ! empty($heroArt['alpha'])])
                     @if ($heroArt) style="--hero-art: url('{{ $heroArt['url'] }}')" @endif aria-label="Event">
                <img @class(['home-hero__logo', 'home-hero__logo--on-dark' => \App\Support\LogoTone::heroIsLight($event)]) src="{{ $event->logoUrl() ?? $event->faviconUrl() ?? '/media/icons/icon-192.png' }}" alt="" width="104" height="104">
                <div class="home-hero__body">
                    <p class="home-hero__eyebrow">Memories</p>
                    <h1 class="home-hero__title">{{ $event->display_name }}</h1>
                    @if ($event->starts_on)
                        <p class="home-hero__meta">
                            {{ \App\Support\SocialKit::dates($event) }}
                        </p>
                    @endif
                </div>
            </section>

            @if ($links === [])
                <div class="card memories__empty">
                    <p class="action-card__title">Nothing here yet</p>
                    <p class="action-card__desc">The photo album and the recorded talks show up here once the organizers share them. Talks usually reach WordPress.tv a few weeks after the event.</p>
                </div>
            @else
                @include('attendee.partials.memories-card', ['event' => $event])
            @endif

            <p class="memories__back">
                @if ($live)
                    <a class="btn btn--outline btn--full" href="{{ route('event.home', $event) }}" data-track="home_link_click" data-track-target="memories_back">Back to {{ $event->display_name }}</a>
                @else
                    <a class="btn btn--outline btn--full" href="{{ route('home') }}#find-your-camp" data-track="home_link_click" data-track-target="find_your_camp">Find your next WordCamp</a>
                @endif
            </p>
        </main>
    </div>

    {{-- Its links' clicks for GA (the page loads no app script): memories_open and home_link_click, with their target. --}}
    <script>
        document.addEventListener('click', function (e) {
            var el = e.target.closest && e.target.closest('[data-track]');
            if (!el || typeof window.gtag !== 'function') return;
            var name = el.dataset.track;
            if (name !== 'memories_open' && name !== 'home_link_click') return;
            try { window.gtag('event', name, { target: el.dataset.trackTarget || '' }); } catch (err) {}
        });
    </script>
</body>
</html>
