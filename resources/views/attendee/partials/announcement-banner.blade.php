@php
    $appBanner = \App\Support\MobileAppConfig::published()['banner'] ?? null;
    if ($appBanner && ! empty($appBanner['until']) && strtotime($appBanner['until']) < now()->timestamp) {
        $appBanner = null;
    }
@endphp

@if ($appBanner)
    @php
        $bannerId = $appBanner['id'] ?? (is_array($appBanner['text'] ?? null) ? ($appBanner['text']['en'] ?? reset($appBanner['text'])) : '');
        $style = $appBanner['style'] ?? 'info';
        $locale = app()->getLocale();
        $isHindi = $locale === 'hi';

        $text = '';
        if (is_array($appBanner['text'] ?? null)) {
            $text = ($isHindi && ! empty($appBanner['text']['hi']))
                ? $appBanner['text']['hi']
                : ($appBanner['text']['en'] ?? reset($appBanner['text']));
        } elseif (is_string($appBanner['text'] ?? null)) {
            $text = $appBanner['text'];
        }

        $kicker = match ($style) {
            'warning' => $isHindi ? 'महत्वपूर्ण सूचना' : 'IMPORTANT NOTICE',
            'success' => $isHindi ? 'ताज़ा अपडेट' : 'NEW UPDATE',
            default => $isHindi ? 'घोषणा' : 'ANNOUNCEMENT',
        };

        $action = $appBanner['action'] ?? null;
        $actionUrl = null;
        $actionLabel = null;
        $actionIsExternal = false;

        if (! empty($action['type'])) {
            if ($action['type'] === 'event' && ! empty($action['slug'])) {
                $actionUrl = route('event.home', $action['slug']);
                $actionLabel = $isHindi ? 'इवेंट खोलें →' : 'View WordCamp →';
            } elseif ($action['type'] === 'url' && ! empty($action['url'])) {
                $actionUrl = $action['url'];
                $actionLabel = $isHindi ? 'लिंक खोलें ↗' : 'Open Link ↗';
                $actionIsExternal = true;
            }
        }
    @endphp

    @if (! empty($text))
        <div class="announcement-banner announcement-banner--{{ $style }}" data-banner-key="{{ $bannerId }}" role="status" aria-label="Announcement">
            <script>
                (function() {
                    var k = @json($bannerId);
                    if (k && localStorage.getItem('campbuddy_dismissed_banner') === k) {
                        var el = document.currentScript.closest('.announcement-banner');
                        if (el) el.style.display = 'none';
                    }
                })();
            </script>
            <div class="announcement-banner__header">
                <div class="announcement-banner__meta">
                    <span class="announcement-banner__icon-box" aria-hidden="true">
                        @if ($style === 'warning')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        @elseif ($style === 'success')
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3L12 3z"/></svg>
                        @else
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 11 18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
                        @endif
                    </span>
                    <span class="announcement-banner__kicker">
                        <span class="announcement-banner__kicker-dot" aria-hidden="true"></span>
                        <span>{{ $kicker }}</span>
                    </span>
                </div>
                <button type="button" class="announcement-banner__close" data-announcement-dismiss aria-label="{{ $isHindi ? 'घोषणा बंद करें' : 'Dismiss announcement' }}">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>

            <p class="announcement-banner__text">{{ $text }}</p>

            @if ($actionUrl)
                <div class="announcement-banner__footer">
                    <a href="{{ $actionUrl }}" class="announcement-banner__cta" @if ($actionIsExternal) target="_blank" rel="noopener noreferrer" @endif>
                        <span>{{ $actionLabel }}</span>
                    </a>
                </div>
            @endif
        </div>

        <script>
            if (!window.__cbBannerInit) {
                window.__cbBannerInit = true;
                document.addEventListener('click', function(e) {
                    var btn = e.target.closest('[data-announcement-dismiss]');
                    if (!btn) return;
                    e.preventDefault();
                    e.stopPropagation();
                    var banner = btn.closest('.announcement-banner');
                    if (!banner) return;
                    var key = banner.getAttribute('data-banner-key');
                    if (key) {
                        try { localStorage.setItem('campbuddy_dismissed_banner', key); } catch(err) {}
                    }
                    banner.style.opacity = '0';
                    banner.style.transform = 'translateY(-6px)';
                    banner.style.maxHeight = '0';
                    banner.style.margin = '0';
                    banner.style.padding = '0';
                    banner.style.borderWidth = '0';
                    setTimeout(function() { banner.remove(); }, 260);
                });
            }
        </script>
    @endif
@endif
