<?php

namespace App\Support;

use App\Models\EventManager;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sign-in tokens for admins and event managers using the native app. Only a
 * SHA-256 of the token is stored; the token itself is shown once, at sign-in,
 * and kept in the phone's Keystore / Keychain. Admin tokens last 12 hours,
 * manager tokens 7 days; every request slides nothing - when it expires the
 * app signs in again.
 */
class AppOrganizer
{
    public const ADMIN_HOURS = 12;

    public const MANAGER_DAYS = 7;

    /** @return array{token: string, expires_at: string} */
    public static function issue(string $role, int $userId, ?string $device): array
    {
        $token = Str::random(60);
        $expires = $role === 'admin' ? now()->addHours(self::ADMIN_HOURS) : now()->addDays(self::MANAGER_DAYS);

        // A person keeps a handful of devices; the oldest sign-ins go first.
        $keep = DB::table('app_api_tokens')->where('role', $role)->where('user_id', $userId)->orderByDesc('id')->limit(4)->pluck('id');
        DB::table('app_api_tokens')->where('role', $role)->where('user_id', $userId)->whereNotIn('id', $keep)->delete();

        DB::table('app_api_tokens')->insert([
            'role' => $role, 'user_id' => $userId, 'device_name' => $device ? mb_substr($device, 0, 80) : null,
            'token_hash' => hash('sha256', $token), 'expires_at' => $expires, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['token' => $token, 'expires_at' => $expires->toIso8601String()];
    }

    /** @return array{role: string, user: User|EventManager, row: object}|null */
    public static function resolve(?string $token): ?array
    {
        if (! $token) {
            return null;
        }

        $row = DB::table('app_api_tokens')->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        if (! $row) {
            return null;
        }

        $user = $row->role === 'admin'
            ? User::find($row->user_id)
            : EventManager::where('is_active', true)->find($row->user_id);

        if (! $user) {
            DB::table('app_api_tokens')->where('id', $row->id)->delete();

            return null;
        }

        DB::table('app_api_tokens')->where('id', $row->id)->update(['last_used_at' => now()]);

        return ['role' => $row->role, 'user' => $user, 'row' => $row];
    }

    public static function revoke(string $token): void
    {
        DB::table('app_api_tokens')->where('token_hash', hash('sha256', $token))->delete();
    }
}
