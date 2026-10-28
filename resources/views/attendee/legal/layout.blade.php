{{-- Shell for the privacy notice and terms: the same top bar and footer as /guide, no event, no tab bar. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle }} | {{ config('campbuddy.name') }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">

    @include('attendee.partials.head-meta', ['manifestUrl' => route('manifest', absolute: false)])


    @vite(['resources/css/fonts.css', 'resources/scss/main.scss', 'resources/js/attendee/app.js'])
</head>
<body class="app-shell" data-page-type="legal">
    @include('attendee.partials.line-icons')
    <div class="app-frame">
        @include('attendee.partials.public-header')

        <main id="main-content" class="landing-main site-main legal-main" tabindex="-1">
            <div class="legal-shell">
                {{-- Top document switcher: fast toggle between Privacy Notice & Terms of Use --}}
                <div class="legal-nav">
                    <nav class="legal-tabs" aria-label="Legal document selection">
                        <a href="{{ route('privacy') }}" class="legal-tab {{ request()->routeIs('privacy') ? 'is-active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            <span>Privacy notice</span>
                        </a>
                        <a href="{{ route('terms') }}" class="legal-tab {{ request()->routeIs('terms') ? 'is-active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            <span>Terms of use</span>
                        </a>
                    </nav>
                </div>

                @yield('content')

                @include('attendee.partials.data-request-section')
            </div>
        </main>

        @include('attendee.partials.footer')
    </div>
</body>
</html>
