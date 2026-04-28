<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\MessageRule;
use App\Models\PlatformConnection;
use App\Models\Record;
use App\Models\TrebelTemplate;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'clients' => Client::count(),
            'connections' => PlatformConnection::count(),
            'templates' => TrebelTemplate::count(),
            'rules' => MessageRule::count(),
            'records' => Record::count(),
            'active_clients' => Client::where('active', true)->count(),
        ];

        return inertia('Dashboard', [
            'stats' => $stats,
            'recent_clients' => Client::query()
                ->withCount(['platformConnections', 'trebelTemplates', 'messageRules'])
                ->orderBy('name')
                ->limit(6)
                ->get()
                ->map(static fn (Client $client): array => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'slug' => $client->slug,
                    'active' => (bool) $client->active,
                    'platform_connections_count' => $client->platform_connections_count,
                    'trebel_templates_count' => $client->trebel_templates_count,
                    'message_rules_count' => $client->message_rules_count,
                ]),
        ]);
    }
}
