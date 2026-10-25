<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Background music from the shared Studio folder (config campbuddy.studio):
 * the library WPSimplified's Reels Studio uses too, in Audio/Library with its
 * tracks.json. The folder sits outside the web root and outside git and the
 * FTP upload, so the Social media page's reels get each track through here.
 * Only files tracks.json lists are served; with no folder there are no
 * tracks, the Reels "Music" list leaves the option out, and nothing breaks.
 */
class StudioMusic
{
    public const LIBRARY = 'Audio/Library';

    /** Which parts of a track suit a reel: the ~30 s reel cut and the seamless loop (stings are too short). */
    private const PARTS = ['reel' => 'reel', 'bed' => 'loop'];

    private const TYPES = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav', 'ogg' => 'audio/ogg'];

    /** The Studio folder: STUDIO_PATH when set, else the first candidate that exists; null when none is there. */
    public static function root(): ?string
    {
        $configured = config('campbuddy.studio.path');
        foreach ($configured ? [$configured] : (array) config('campbuddy.studio.candidates', []) as $dir) {
            // @: a host's open_basedir may hide a candidate.
            if (is_string($dir) && $dir !== '' && @is_dir($dir)) {
                return rtrim(str_replace('\\', '/', $dir), '/');
            }
        }

        return null;
    }

    /**
     * The tracks a reel can use, those whose file is there: reel cuts, loops, then full tracks.
     *
     * @return list<array{file: string, title: string, kind: 'reel'|'loop'|'full', seconds: int}>
     */
    public static function tracks(): array
    {
        $library = self::library();
        $manifest = $library ? $library.'/tracks.json' : null;
        if (! $manifest || ! is_file($manifest)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($manifest), true);
        $out = ['reel' => [], 'loop' => [], 'full' => []];
        foreach ((array) ($data['tracks'] ?? []) as $track) {
            $title = trim((string) ($track['title'] ?? ''));
            $out['full'][] = self::entry($library, $track['filename'] ?? null, $title, 'full', $track['duration'] ?? 0);
            foreach ((array) ($track['parts'] ?? []) as $part) {
                $kind = self::PARTS[$part['id'] ?? ''] ?? null;
                if ($kind) {
                    $out[$kind][] = self::entry($library, $part['filename'] ?? null, $title, $kind, $part['duration'] ?? 0);
                }
            }
        }

        return array_values(array_filter(array_merge($out['reel'], $out['loop'], $out['full'])));
    }

    /** One library track, as a file response (Range requests work, so a player can seek); 404 for anything not listed. */
    public static function respond(string $file): Response
    {
        $listed = in_array($file, array_column(self::tracks(), 'file'), true);
        $path = $listed ? realpath(self::library().'/'.$file) : false;
        $library = realpath((string) self::library());
        abort_unless($path && $library && str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $library).'/'), 404);

        // Private: only signed-in organizers get here, so no shared cache (Cloudflare) may keep it.
        return response()->file($path, ['Content-Type' => self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))]])
            ->setPrivate()->setMaxAge(86400);
    }

    private static function library(): ?string
    {
        $root = self::root();

        return $root ? $root.'/'.self::LIBRARY : null;
    }

    /** @return array{file: string, title: string, kind: string, seconds: int}|null */
    private static function entry(string $library, mixed $file, string $title, string $kind, mixed $seconds): ?array
    {
        $file = is_string($file) ? str_replace('\\', '/', $file) : '';
        $ok = $file !== '' && $title !== '' && ! str_contains($file, '..')
            && isset(self::TYPES[strtolower(pathinfo($file, PATHINFO_EXTENSION))]) && is_file($library.'/'.$file);

        return $ok ? ['file' => $file, 'title' => $title, 'kind' => $kind, 'seconds' => (int) round((float) $seconds)] : null;
    }
}
