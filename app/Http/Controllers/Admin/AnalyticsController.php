<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Support\AnalyticsReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Google Analytics, read inside the panel: one stream of the GA4 property,
 * any date range, bots left out by default. See App\Support\AnalyticsReport.
 */
class AnalyticsController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = AnalyticsReport::filters($request);
        $streams = [];
        $report = null;
        $error = AnalyticsReport::setupProblem();

        if ($error === null) {
            try {
                $streams = AnalyticsReport::streams();
                $filters['stream'] = array_key_exists((string) $filters['stream'], $streams) ? $filters['stream'] : array_key_first($streams);

                if ($filters['stream'] === null) {
                    $error = 'The GA property has no data streams.';
                } else {
                    $report = AnalyticsReport::build($filters['stream'], $filters['from'], $filters['to'], $filters['bots'], $request->boolean('fresh'));
                }
            } catch (\Throwable $e) {
                report($e);
                $error = AnalyticsReport::explain($e);
            }
        }

        return view('admin.analytics.index', [
            'filters' => $filters,
            'periods' => AnalyticsReport::PERIODS,
            'streams' => $streams,
            'report' => $report,
            'error' => $error,
            'eventNames' => Event::pluck('display_name', 'slug')->all(),
        ]);
    }
}
