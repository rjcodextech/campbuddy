{{--
    Shared shell for every error page. Deliberately self-contained — inline
    styles, no @vite, no database — because an error page has to render
    even when the thing that broke is the asset build or the database.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>@yield('title') | {{ config('campbuddy.name', 'CampBuddy') }}</title>
    <link rel="icon" href="/media/icons/favicon-32.png" type="image/png">
    {{-- Counts broken links and outages in GA (a page_view titled with the
    error). No app bundle here, so just the tag. --}}
    @include('attendee.partials.analytics')
    <style>
        :root { --ink:#2b1a14; --muted:#6b5a52; --paper:#fffaf4; --line:#eaded3; --brand:#c33a19; --brand-dark:#8f2a12; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px 16px;
               font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background:var(--paper); color:var(--ink); }
        main { width:100%; max-width:420px; text-align:center; }
        .art { width:160px; height:auto; margin:0 auto 20px; display:block; }
        .code { font-size:.8rem; font-weight:700; letter-spacing:.12em; text-transform:uppercase; color:var(--brand); margin:0 0 6px; }
        h1 { font-size:1.45rem; line-height:1.25; margin:0 0 10px; }
        p { color:var(--muted); line-height:1.55; margin:0 0 22px; }
        .actions { display:flex; flex-direction:column; gap:10px; }
        a.btn, button.btn { display:block; font:inherit; font-weight:600; text-decoration:none; padding:13px 20px; border-radius:10px; border:0; cursor:pointer; }
        .btn--primary { background:var(--brand); color:#fff; }
        .btn--primary:hover { background:var(--brand-dark); }
        .btn--ghost { background:transparent; color:var(--ink); border:1px solid var(--line) !important; }
        a:focus-visible, button:focus-visible { outline:3px solid #f2b705; outline-offset:2px; }
    </style>
</head>
<body>
    <main>
        <img class="art" src="/media/illustrations/@yield('art', 'lost').svg" alt="" width="160" height="120">
        <p class="code">@yield('code')</p>
        <h1>@yield('heading')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            @yield('actions')
            <a class="btn btn--ghost" href="/">Back to all WordCamps</a>
        </div>
    </main>
</body>
</html>
