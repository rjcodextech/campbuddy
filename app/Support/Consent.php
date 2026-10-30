<?php

namespace App\Support;

use App\Models\ConsentLog;
use App\Models\Event;
use Illuminate\Http\Request;
use Throwable;

/**
 * Consent under the DPDP Act 2023 (s.6): before attendee discovery, a shared Camp Card or a deal form
 * keeps anything, the person ticks "I agree … and I am 18 or older, or my parent or guardian agrees"
 * (s.9). The tick travels as `consent`; the proof (which notice, when, from where) goes to consent_logs,
 * which are erased with the rest of the event's shared data (PersonalDataErasure).
 *
 * The tick is required from every client that does the handshake (X-CampBuddy-Client: the website and
 * app versions with the box). App versions and cached pages already on phones don't have the box; they
 * showed the earlier notice, so their consent is logged as "legacy" until strict mode is on.
 */
class Consent
{
    public static function required(Request $request): bool
    {
        return $request->hasHeader('X-CampBuddy-Client') || (bool) config('campbuddy.handshake.strict', false);
    }

    /** The validation rule for `consent` in a form request. */
    public static function rule(Request $request): array
    {
        return self::required($request) ? ['accepted'] : ['sometimes', 'accepted'];
    }

    public const MESSAGE = 'Please tick the box to agree before you go on.';

    /**
     * @param  bool  $actionIsConsent  true where the action itself is the agreement, taken right under the notice
     *                                 (a deal form's "Continue": "By tapping Continue you agree …")
     */
    public static function record(Request $request, Event $event, string $subjectType, string $subjectId, string $purpose, bool $actionIsConsent = false): void
    {
        try {
            ConsentLog::create([
                'event_id' => $event->id,
                'subject_type' => $subjectType,
                'subject_identifier' => $subjectId,
                'purpose' => $purpose,
                'notice_version' => $actionIsConsent || $request->boolean('consent') ? (string) config('campbuddy.legal.notice_version') : 'legacy',
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'consented_at' => now(),
            ]);
        } catch (Throwable $e) {
            // The person's action already succeeded; a missing log is an operator problem, reported, not theirs.
            report($e);
        }
    }
}
