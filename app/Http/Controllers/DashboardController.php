<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Platform;
use App\Models\Record;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'events' => Event::count(),
            'platforms' => Platform::count(),
            'records' => Record::count(),
            'active_events' => Event::where('active', true)->count(),
        ];

        return inertia('Dashboard', [
            'stats' => $stats,
        ]);
    }
}
