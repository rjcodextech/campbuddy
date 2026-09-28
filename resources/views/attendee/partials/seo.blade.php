{{--
    Search, social and answer-engine metadata for one page (App\Support\Seo).

    Expects:
      $seoTitle       — the full <title> text
      $seoDescription — one or two plain sentences
      $seoRobots      — optional, default "index, follow, max-image-preview:large"
      $seoSchema      — optional list of schema.org nodes for JSON-LD
--}}
@php
    $seoRobots ??= 'index, follow, max-image-preview:large';
    $seoImage = \App\Support\Seo::absolute(\App\Support\Seo::IMAGE);
    $seoCanonical = url()->current();
@endphp
<meta name="description" content="{{ $seoDescription }}">
<meta name="robots" content="{{ $seoRobots }}">
<link rel="canonical" href="{{ $seoCanonical }}">
<link rel="sitemap" type="application/xml" href="{{ route('sitemap') }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('campbuddy.name') }}">
<meta property="og:locale" content="en_US">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
<meta property="og:url" content="{{ $seoCanonical }}">
<meta property="og:image" content="{{ $seoImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="CampBuddy — your WordCamp companion">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
<meta name="twitter:image" content="{{ $seoImage }}">

@if ($google = config('services.search.google_verification'))
    <meta name="google-site-verification" content="{{ $google }}">
@endif
@if ($bing = config('services.search.bing_verification'))
    <meta name="msvalidate.01" content="{{ $bing }}">
@endif

@if (! empty($seoSchema))
    <script type="application/ld+json">{!! \App\Support\Seo::jsonLd($seoSchema) !!}</script>
@endif
