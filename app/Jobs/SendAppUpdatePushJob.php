<?php

namespace App\Jobs;

use App\Models\AppRelease;
use App\Models\NativePushToken;
use App\Support\NativePush;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Tells every Android install older than a just-published release (App\Support\AppReleases)
 * to fetch it now. A silent data message: the APK app checks, downloads and installs; the
 * Play build and older apps ignore it. Phones that miss it still find the update the next
 * time the app opens or within a few hours, so a failed push costs nothing but time.
 */
class SendAppUpdatePushJob implements ShouldQueue
{
    use Queueable;

    public const TTL_SECONDS = 7 * 86400;

    /** Never twice: the push is only a nudge, the phone checks on its own anyway. */
    public int $tries = 1;

    public function __construct(public int $releaseId) {}

    public function handle(): void
    {
        $release = AppRelease::find($this->releaseId);
        if (! $release || $release->status !== AppRelease::LIVE || ! NativePush::enabled()) {
            return;
        }

        // One token per phone (the newest), and only phones that last reported an older version.
        $tokens = NativePushToken::where('platform', 'android')->orderByDesc('updated_at')->orderByDesc('id')->get()
            ->unique('device_id')
            ->filter(fn ($t) => $t->app_version === null || version_compare($t->app_version, $release->version_name, '<'))
            ->values();

        $sent = 0;
        foreach ($tokens->chunk(500) as $chunk) {
            $sent += NativePush::toTokens($chunk->values(), [
                'version_code' => (string) $release->version_code,
                'required' => $release->required ? '1' : '0',
            ], 'app-update', self::TTL_SECONDS, silent: true)['sent'];
        }

        $release->update(['pushed_to' => $sent]);
        Log::info('App update pushed', ['release' => $release->version_name, 'phones' => $sent]);
    }
}
