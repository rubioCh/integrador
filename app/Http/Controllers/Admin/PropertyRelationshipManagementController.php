<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Property;
use App\Models\PropertyRelationship;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PropertyRelationshipManagementController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $this->assertPropertyScope($event, $data['property_id'], $data['related_property_id']);

        PropertyRelationship::query()->create([
            'event_id' => $event->id,
            'property_id' => $data['property_id'],
            'related_property_id' => $data['related_property_id'],
            'mapping_key' => $data['mapping_key'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
            'meta' => $data['meta'] ?? [],
        ]);

        return redirect()
            ->route('admin.events.relationships', $event)
            ->with('success', 'Relación de propiedades creada correctamente.');
    }

    public function update(Request $request, Event $event, PropertyRelationship $relationship): RedirectResponse
    {
        abort_unless($relationship->event_id === $event->id, 404);

        $data = $this->validatePayload($request);
        $this->assertPropertyScope($event, $data['property_id'], $data['related_property_id']);

        $relationship->update([
            'property_id' => $data['property_id'],
            'related_property_id' => $data['related_property_id'],
            'mapping_key' => $data['mapping_key'] ?? null,
            'active' => (bool) ($data['active'] ?? false),
            'meta' => $data['meta'] ?? [],
        ]);

        return redirect()
            ->route('admin.events.relationships', $event)
            ->with('success', 'Relación de propiedades actualizada correctamente.');
    }

    public function destroy(Event $event, PropertyRelationship $relationship): RedirectResponse
    {
        abort_unless($relationship->event_id === $event->id, 404);

        $relationship->delete();

        return redirect()
            ->route('admin.events.relationships', $event)
            ->with('success', 'Relación de propiedades eliminada correctamente.');
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'property_id' => ['required', 'integer', 'exists:properties,id'],
            'related_property_id' => ['required', 'integer', 'exists:properties,id'],
            'mapping_key' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'meta' => ['sometimes', 'array'],
        ]);
    }

    private function assertPropertyScope(Event $event, int $sourcePropertyId, int $targetPropertyId): void
    {
        $source = Property::query()->findOrFail($sourcePropertyId);
        $target = Property::query()->findOrFail($targetPropertyId);
        $targetPlatformId = $event->to_event?->platform_id ?? $event->platform_id;

        if ($source->platform_id !== $event->platform_id) {
            abort(422, 'La propiedad origen no pertenece a la plataforma del evento.');
        }

        if ($target->platform_id !== $targetPlatformId) {
            abort(422, 'La propiedad destino no pertenece a la plataforma objetivo del evento.');
        }
    }
}
