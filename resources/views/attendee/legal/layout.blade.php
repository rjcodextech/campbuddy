{{-- Unified Layout for Privacy Notice & Terms of Use: matches /guide and /about design system. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle }} | {{ config('campbuddy.name') }}</title>
    <meta name="description" content="{{ $pageDescription }}">
    <link rel="canonical" href="{{ url()->current() }}">

    @include('attendee.partials.head-meta', ['manifestUrl' => route('manifest', absolute: false)])

    @include('attendee.partials.analytics')

    @vite(['resources/css/fonts.css', 'resources/scss/main.scss', 'resources/js/attendee/app.js'])
</head>
<body class="app-shell" data-page-type="legal">
    @include('attendee.partials.line-icons')
    <div class="app-frame">
        @include('attendee.partials.desktop-notice')

        @include('attendee.partials.public-header')

        <main id="main-content" class="landing-main site-main legal-main" tabindex="-1">
            {{-- Fast document toggle --}}
            <div class="legal-nav" style="margin-bottom:20px">
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

            <div class="guide-doc-layout">
                @hasSection('toc')
                    <aside class="guide-doc-sidebar" aria-label="Document Navigation">
                        <div class="guide-doc-sidebar__card">
                            <div class="guide-doc-sidebar__head">
                                <span class="guide-doc-sidebar__title">
                                    <x-attendee.line-icon name="book-open" />
                                    <span>On This Page</span>
                                </span>
                            </div>
                            <nav class="guide-jump guide-jump--sidebar" aria-label="Legal sections">
                                @yield('toc')
                            </nav>
                        </div>
                    </aside>
                @endif

                <div class="guide-doc-content">
                    @yield('content')

                    @include('attendee.partials.data-request-section')
                </div>
            </div>
        </main>

        @include('attendee.partials.footer')
    </div>
</body>
</html>
