<?php

namespace App\Http\Controllers;

use App\Support\SystemHealth;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        // Yes/no per check only — details are for the admin dashboard.
        $checks = SystemHealth::checks();

        return response()
            ->json(['status' => in_array(false, $checks, true) ? 'degraded' : 'ok', 'checks' => $checks])
            ->header('Cache-Control', 'no-store, max-age=0');
    }
}
