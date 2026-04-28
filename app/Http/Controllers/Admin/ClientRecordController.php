<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Record;

class ClientRecordController extends Controller
{
    public function index(Client $client)
    {
        $records = Record::query()
            ->where('client_id', $client->id)
            ->latest('id')
            ->paginate(20)
            ->through(static function (Record $record): array {
                return [
                    'id' => $record->id,
                    'event_type' => $record->event_type,
                    'status' => $record->status,
                    'message' => $record->message,
                    'payload' => $record->payload ?? [],
                    'details' => $record->details ?? [],
                    'created_at' => optional($record->created_at)?->toDateTimeString(),
                ];
            });

        return inertia('Admin/ClientRecords', [
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'slug' => $client->slug,
            ],
            'records' => $records,
        ]);
    }
}
