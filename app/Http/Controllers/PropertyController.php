<?php

namespace App\Http\Controllers;

use App\Models\Property;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(): JsonResponse
    {
        $properties = Property::query()->with(['platform'])->paginate(25);

        return response()->json($properties);
    }

    public function show(Property $property): JsonResponse
    {
        $property->load(['platform', 'categories', 'events']);

        return response()->json($property);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'platform_id' => ['required', 'integer', 'exists:platforms,id'],
            'name' => ['required', 'string', 'max:255'],
            'key' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'max:50'],
            'required' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'meta' => ['sometimes', 'array'],
        ]);

        $property = Property::query()->create($data);

        return response()->json($property, 201);
    }

    public function update(Request $request, Property $property): JsonResponse
    {
        $data = $request->validate([
            'platform_id' => ['sometimes', 'integer', 'exists:platforms,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'key' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:50'],
            'required' => ['sometimes', 'boolean'],
            'active' => ['sometimes', 'boolean'],
            'meta' => ['sometimes', 'array'],
        ]);

        $property->update($data);

        return response()->json($property);
    }

    public function destroy(Property $property): JsonResponse
    {
        $property->delete();

        return response()->json(['success' => true]);
    }
}
