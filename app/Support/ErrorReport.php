<?php

namespace App\Support;

use App\Models\FetchLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * What is going wrong, for the admin's Errors page and the one-line summary
 * on the dashboard: the system checks (SystemHealth) and the fetches that
 * failed or only partly worked. The same failure repeating every run is one
 * row with a count, not fifty.
 */
class ErrorReport
{
    /**
     * Look-back choices for fetch problems: label => days. The log is kept for
     * FetchLogRetention::KEEP_DAYS (7), so nothing longer is offered.
     */
    public const PERIODS = ['1' => 'Last 24 hours', '3' => 'Last 3 days', '7' => 'Last 7 days'];

    public const DEFAULT_PERIOD = '7';

    /** What each fetch job is called in the panel. */
    public const JOB_LABELS = [
        'sessions_speakers_sponsors' => 'Schedule',
        'roster' => 'Attendee list',
        'event_info' => 'Event information',
        'branding' => 'Branding',
    ];

    /**
     * @return array{period: string, event: ?int, job: ?string, result: ?string}
     */
    public static function filters(\Illuminate\Http\Request $request): array
    {
        $event = $request->query('event');

        return [
            'period' => array_key_exists((string) $request->query('period'), self::PERIODS) ? (string) $request->query('period') : self::DEFAULT_PERIOD,
            'event' => is_numeric($event) && (int) $event > 0 ? (int) $event : null,
            'job' => array_key_exists((string) $request->query('job'), self::JOB_LABELS) ? (string) $request->query('job') : null,
            'result' => in_array($request->query('result'), ['error', 'partial'], true) ? $request->query('result') : null,
        ];
    }

    /**
     * Failed or partly-failed fetches, one row per repeated failure, newest first.
     *
     * @param  array{period: string, event: ?int, job: ?string, result: ?string}  $filters
     */
    public static function fetchProblems(array $filters, int $perPage = 20): LengthAwarePaginator
    {
        return self::fetchQuery($filters)
            ->selectRaw('event_id, job_type, status, message, count(*) as times, max(fetched_at) as last_at')
            ->groupBy('event_id', 'job_type', 'status', 'message')
            ->orderByDesc('last_at')
            ->with('event:id,display_name,slug')
            ->paginate($perPage)
            ->withQueryString();
    }

    /** How many distinct fetch problems there are in the default look-back (for the dashboard strip). */
    public static function fetchProblemCount(): int
    {
        return self::fetchQuery(['period' => self::DEFAULT_PERIOD, 'event' => null, 'job' => null, 'result' => null])
            ->toBase()
            ->select('event_id', 'job_type', 'status', 'message')
            ->groupBy('event_id', 'job_type', 'status', 'message')
            ->get()
            ->count();
    }

    /**
     * The one-line picture the dashboard shows.
     *
     * @return array{system: int, critical: int, fetch: int, total: int}
     */
    public static function summary(): array
    {
        $problems = SystemHealth::problems();
        $critical = count(array_filter($problems, fn (array $p) => $p['level'] === 'error'));
        $fetch = self::fetchProblemCount();

        return ['system' => count($problems), 'critical' => $critical, 'fetch' => $fetch, 'total' => count($problems) + $fetch];
    }

    /**
     * @param  array{period: string, event: ?int, job: ?string, result: ?string}  $filters
     * @return \Illuminate\Database\Eloquent\Builder<FetchLog>
     */
    private static function fetchQuery(array $filters)
    {
        return FetchLog::query()
            ->where('status', '!=', 'ok')
            ->where('fetched_at', '>=', now()->subDays((int) $filters['period']))
            ->when($filters['event'], fn ($q, int $id) => $q->where('event_id', $id))
            ->when($filters['job'], fn ($q, string $job) => $q->where('job_type', $job))
            ->when($filters['result'], fn ($q, string $result) => $q->where('status', $result));
    }
}
