<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PropertyManagementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'platform_id' => ['required', 'integer', 'exists:platforms,id'],
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['string', 'integer', 'float', 'boolean', 'datetime', 'file'])],
            'required' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'meta' => ['sometimes', 'array'],
        ]);

        Property::query()->create([
            'platform_id' => $data['platform_id'],
            'name' => $data['name'],
            'key' => $data['key'],
            'type' => $data['type'] ?? 'string',
            'required' => (bool) ($data['required'] ?? false),
            'active' => (bool) ($data['active'] ?? true),
            'meta' => $data['meta'] ?? [],
        ]);

        return redirect()->route('admin.properties')->with('success', 'Propiedad creada correctamente.');
    }

    public function update(Request $request, Property $property): RedirectResponse
    {
        $data = $request->validate([
            'platform_id' => ['required', 'integer', 'exists:platforms,id'],
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255'],
            'type' => ['nullable', Rule::in(['string', 'integer', 'float', 'boolean', 'datetime', 'file'])],
            'required' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'meta' => ['sometimes', 'array'],
        ]);

        $property->update([
            'platform_id' => $data['platform_id'],
            'name' => $data['name'],
            'key' => $data['key'],
            'type' => $data['type'] ?? 'string',
            'required' => (bool) ($data['required'] ?? false),
            'active' => (bool) ($data['active'] ?? false),
            'meta' => $data['meta'] ?? [],
        ]);

        return redirect()->route('admin.properties')->with('success', 'Propiedad actualizada correctamente.');
    }

    public function destroy(Property $property): RedirectResponse
    {
        $property->delete();

        return redirect()->route('admin.properties')->with('success', 'Propiedad eliminada correctamente.');
    }
}
