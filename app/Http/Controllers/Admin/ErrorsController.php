<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\ErrorReport;
use App\Support\SystemHealth;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Everything that is going wrong in one place: the system checks (cron,
 * migrations, stuck jobs, live events without data) and the fetches that
 * failed. The dashboard only shows a one-line summary and links here.
 */
class ErrorsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = ErrorReport::filters($request);

        return view('admin.errors.index', [
            'problems' => SystemHealth::problems(),
            'filters' => $filters,
            'fetchProblems' => ErrorReport::fetchProblems($filters),
            'events' => Event::orderBy('display_name')->pluck('display_name', 'id'),
            'periods' => ErrorReport::PERIODS,
            'jobLabels' => ErrorReport::JOB_LABELS,
        ]);
    }
}
