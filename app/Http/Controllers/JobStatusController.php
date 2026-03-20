<?php

namespace App\Http\Controllers;

use App\Models\Record;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobStatusController extends Controller
{
    public function checkJobStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'record_id' => ['required', 'integer', 'exists:records,id'],
        ]);

        $record = Record::query()->find($data['record_id']);

        return response()->json([
            'success' => true,
            'status' => $record->status,
            'message' => $record->message,
            'details' => $record->details,
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
        ]);
    }

    public function checkRelatedRecords(Request $request): JsonResponse
    {
        $data = $request->validate([
            'parent_record_id' => ['required', 'integer', 'exists:records,id'],
        ]);

        $records = Record::query()
            ->where('record_id', $data['parent_record_id'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'records' => $records,
            'total' => $records->count(),
            'latest_status' => $records->first()?->status ?? 'unknown',
        ]);
    }
}
