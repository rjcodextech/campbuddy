{{-- The newest Android APK (Admin → Mobile app → App updates), in the footer links; only while a version is published. --}}
@if (\App\Support\AppReleases::hasLiveApk())
    <a href="{{ route('app.android') }}" rel="nofollow">Android app (APK)</a>
@endif
