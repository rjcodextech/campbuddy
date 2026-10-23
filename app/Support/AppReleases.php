<?php

namespace App\Support;

use App\Jobs\SendAppUpdatePushJob;
use App\Models\AppRelease;
use App\Models\NativePushToken;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Android app updates outside the Play Store (Admin → Mobile app → App updates).
 *
 * The admin uploads the release APK in pieces (shared hosting caps one request
 * at a few MB), the server reads it (ApkInspector) and refuses anything a phone
 * would refuse later: another app, an older or equal version, another signing
 * key. Publishing makes it the newest live release; the APK app asks
 * /api/v1/app/update when it opens and every few hours, and gets a silent push.
 */
final class AppReleases
{
    public const MAX_SIZE = 150 * 1024 * 1024;

    private const DIR = 'app-releases';

    /** Packages that may be uploaded: the store id, plus the debug id on a development machine. */
    public static function allowedPackages(): array
    {
        $id = (string) config('mobile_app.app_id');

        return app()->environment('local', 'testing') ? [$id, $id.'.native'] : [$id];
    }

    public static function latest(string $package): ?AppRelease
    {
        return AppRelease::where('package', $package)->where('status', AppRelease::LIVE)->orderByDesc('version_code')->first();
    }

    /** Builds below this versionCode must update before they can be used (0 = none). */
    public static function requiredCode(string $package): int
    {
        return (int) AppRelease::where('package', $package)->where('status', AppRelease::LIVE)->where('required', true)->max('version_code');
    }

    /** What /api/v1/app/update answers a phone running $code of $package. */
    public static function forApi(string $package, int $code): array
    {
        $latest = self::latest($package);

        return [
            'package' => $package,
            'update' => $latest !== null && $latest->version_code > $code,
            'required_code' => self::requiredCode($package),
            'latest' => $latest === null ? null : [
                'version_code' => $latest->version_code,
                'version_name' => $latest->version_name,
                'size' => $latest->size,
                'sha256' => $latest->sha256,
                'cert_sha256' => $latest->cert_sha256,
                'url' => $latest->downloadUrl(),
                'required' => $latest->required,
                'notes' => ['en' => (string) $latest->notes_en, 'hi' => (string) $latest->notes_hi],
                'published_at' => $latest->published_at?->toIso8601String(),
            ],
        ];
    }

    /** The biggest piece the server accepts in one request (from post_max_size / upload limits), 256 KB – 4 MB. */
    public static function chunkSize(): int
    {
        $limits = array_filter([self::iniBytes('post_max_size'), self::iniBytes('upload_max_filesize')]);
        $limit = $limits ? min($limits) : 8 * 1024 * 1024;

        return max(256 * 1024, min(4 * 1024 * 1024, $limit - 64 * 1024));
    }

    /**
     * Adds piece $index of upload $id. A piece that already arrived (a retry after a lost answer) is
     * accepted again without writing; a gap is refused. Returns the next piece expected.
     */
    public static function receiveChunk(string $id, int $index, string $bytes): int
    {
        self::assertUploadId($id);
        if ($index === 0) {
            self::sweepTemp();
        }
        [$part, $state] = self::tempPaths($id);
        $next = is_file($state) ? (int) file_get_contents($state) : 0;

        if ($index < $next) {
            return $next;
        }
        if ($index > $next) {
            throw new InvalidArgumentException("Piece {$index} arrived before piece {$next}. Please upload again.");
        }
        if ($bytes === '' || strlen($bytes) > self::chunkSize()) {
            throw new InvalidArgumentException('A piece of the upload was empty or too big. Please upload again.');
        }
        if ((is_file($part) ? filesize($part) : 0) + strlen($bytes) > self::MAX_SIZE) {
            throw new InvalidArgumentException('The APK is bigger than '.(self::MAX_SIZE >> 20).' MB.');
        }

        File::ensureDirectoryExists(dirname($part));
        if (file_put_contents($part, $bytes, FILE_APPEND | LOCK_EX) === false) {
            throw new InvalidArgumentException('The server could not save the upload (disk full?).');
        }
        file_put_contents($state, (string) ($next + 1), LOCK_EX);

        return $next + 1;
    }

    /**
     * Checks the finished upload and keeps it as a draft release.
     *
     * @param  array{notes_en?: ?string, notes_hi?: ?string, required?: bool}  $fields
     */
    public static function finishUpload(string $id, string $sha256, array $fields, ?User $user): AppRelease
    {
        self::assertUploadId($id);
        [$part, $state] = self::tempPaths($id);

        try {
            if (! is_file($part) || filesize($part) === 0) {
                throw new InvalidArgumentException('The upload did not arrive. Please upload again.');
            }
            $hash = hash_file('sha256', $part);
            if (! hash_equals($hash, strtolower($sha256))) {
                throw new InvalidArgumentException('The file was damaged on the way (its checksum does not match). Please upload again.');
            }

            $apk = ApkInspector::inspect($part);
            self::assertAcceptable($apk);

            $file = self::DIR.'/'.$apk['package'].'/'.$apk['version_code'].'-'.substr($hash, 0, 12).'.apk';
            $target = Storage::disk('local')->path($file);
            File::ensureDirectoryExists(dirname($target));
            if (! @rename($part, $target)) {
                throw new InvalidArgumentException('The server could not store the APK.');
            }

            return AppRelease::create([
                'platform' => 'android',
                'package' => $apk['package'],
                'version_code' => $apk['version_code'],
                'version_name' => $apk['version_name'],
                'min_sdk' => $apk['min_sdk'],
                'file' => $file,
                'size' => filesize($target),
                'sha256' => $hash,
                'cert_sha256' => $apk['cert_sha256'],
                'notes_en' => self::note($fields['notes_en'] ?? null),
                'notes_hi' => self::note($fields['notes_hi'] ?? null),
                'required' => (bool) ($fields['required'] ?? false),
                'status' => AppRelease::DRAFT,
                'uploaded_by' => $user?->id,
            ]);
        } finally {
            @unlink($part);
            @unlink($state);
        }
    }

    /** Refuses what Android would refuse on the phone, with the reason. */
    public static function assertAcceptable(array $apk): void
    {
        $id = (string) config('mobile_app.app_id');
        if (! in_array($apk['package'], self::allowedPackages(), true)) {
            $hint = str_starts_with($apk['package'], $id.'.') ? ' This looks like a debug build: upload the release APK (bash native-app/build-native.sh release).' : '';
            throw new InvalidArgumentException("This APK is the app \"{$apk['package']}\", not CampBuddy ({$id}).{$hint}");
        }

        $newest = AppRelease::where('package', $apk['package'])->orderByDesc('version_code')->first();
        if ($newest !== null && $apk['version_code'] <= $newest->version_code) {
            throw new InvalidArgumentException("Version {$apk['version_name']} (build {$apk['version_code']}) is not newer than {$newest->version_name} (build {$newest->version_code}), which is already uploaded. Phones only take a higher build number: make a new build.");
        }
        if ($newest !== null && ! hash_equals($newest->cert_sha256, $apk['cert_sha256'])) {
            throw new InvalidArgumentException("This APK is signed with a different key than {$newest->version_name}. Phones would refuse it as an update: sign it with the same upload key.");
        }

        $known = array_map(fn ($f) => strtolower(str_replace(':', '', $f)), (array) config('mobile_app.android_sha256'));
        if ($apk['package'] === $id && $known !== [] && ! in_array($apk['cert_sha256'], $known, true)) {
            throw new InvalidArgumentException('This APK is not signed with CampBuddy\'s key (its certificate is not in MOBILE_ANDROID_SHA256).');
        }
    }

    public static function publish(AppRelease $release, ?User $user): void
    {
        $release->update(['status' => AppRelease::LIVE, 'published_at' => now(), 'published_by' => $user?->id]);
        SendAppUpdatePushJob::dispatch($release->id);
    }

    public static function withdraw(AppRelease $release): void
    {
        $release->update(['status' => AppRelease::WITHDRAWN]);
    }

    public static function delete(AppRelease $release): void
    {
        Storage::disk('local')->delete($release->file);
        $release->delete();
    }

    public static function path(AppRelease $release): string
    {
        return Storage::disk('local')->path($release->file);
    }

    /**
     * Android phones by the app version they last reported with a push token (newest first).
     *
     * @return list<array{version: string, phones: int}>
     */
    public static function adoption(): array
    {
        $counts = NativePushToken::where('platform', 'android')->whereNotNull('app_version')->orderByDesc('updated_at')
            ->get(['device_id', 'app_version'])->unique('device_id')->countBy('app_version')->all();
        uksort($counts, fn ($a, $b) => version_compare((string) $b, (string) $a));

        return array_map(fn ($version, $phones) => ['version' => (string) $version, 'phones' => $phones], array_keys($counts), $counts);
    }

    private static function note(?string $text): ?string
    {
        $text = trim(strip_tags((string) $text));

        return $text === '' ? null : Str::limit($text, 500, '');
    }

    private static function assertUploadId(string $id): void
    {
        if (! preg_match('/^[a-f0-9-]{36}$/', $id)) {
            throw new InvalidArgumentException('Unknown upload.');
        }
    }

    /** @return array{0: string, 1: string} */
    private static function tempPaths(string $id): array
    {
        $dir = Storage::disk('local')->path(self::DIR.'/tmp');

        return [$dir.'/'.$id.'.part', $dir.'/'.$id.'.next'];
    }

    /** Uploads left half-done for a day are thrown away. */
    private static function sweepTemp(): void
    {
        foreach (glob(Storage::disk('local')->path(self::DIR.'/tmp').'/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }
    }

    private static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        if ($value === '' || $value === '0' || $value === '-1') {
            return 0;
        }
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number << 30,
            'm' => $number << 20,
            'k' => $number << 10,
            default => $number,
        };
    }
}
