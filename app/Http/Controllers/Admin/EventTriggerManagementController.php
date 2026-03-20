<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\EventTriggerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EventTriggerManagementController extends Controller
{
    public function show(Event $event, EventTriggerService $eventTriggerService)
    {
        return inertia('Admin/EventTriggers', [
            'event' => [
                'id' => $event->id,
                'name' => $event->name,
                'event_type_id' => $event->event_type_id,
                'platform_id' => $event->platform_id,
            ],
            'triggers' => $eventTriggerService->getEventTriggers($event),
            'supported_operators' => EventTriggerService::SUPPORTED_OPERATORS,
        ]);
    }

    public function update(Request $request, Event $event, EventTriggerService $eventTriggerService): RedirectResponse
    {
        $data = $request->validate([
            'groups' => ['required', 'array'],
            'groups.*.id' => ['nullable', 'integer', 'exists:event_trigger_groups,id'],
            'groups.*.name' => ['required', 'string', 'max:255'],
            'groups.*.operator' => ['required', Rule::in(['and', 'or'])],
            'groups.*.active' => ['sometimes', 'boolean'],
            'groups.*.conditions' => ['required', 'array', 'min:1'],
            'groups.*.conditions.*.id' => ['nullable', 'integer', 'exists:event_trigger_group_conditions,id'],
            'groups.*.conditions.*.field' => ['required', 'string', 'max:255'],
            'groups.*.conditions.*.operator' => ['required', Rule::in(EventTriggerService::SUPPORTED_OPERATORS)],
            'groups.*.conditions.*.value' => ['nullable'],
        ]);

        $eventTriggerService->syncEventTriggers($event, $data['groups']);

        return back()->with('success', 'Triggers actualizados correctamente.');
    }
}
