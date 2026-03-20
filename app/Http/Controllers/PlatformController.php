<?php

namespace App\Http\Controllers;

use App\Models\Platform;
use App\Services\EventProcessingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlatformController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Platform::query();

        if ($request->filled('type')) {
            $query->where('type', (string) $request->input('type'));
        }

        if ($request->has('active')) {
            $query->where('active', filter_var($request->input('active'), FILTER_VALIDATE_BOOL));
        }

        $perPage = max(1, min((int) $request->input('per_page', 20), 100));
        $platforms = $query
            ->orderBy('name')
            ->paginate($perPage)
            ->through(fn (Platform $platform): array => $this->serializePlatform($platform));

        return response()->json([
            'success' => true,
            'data' => $platforms,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:platforms,slug'],
            'type' => ['required', Rule::in(['hubspot', 'odoo', 'netsuite', 'generic'])],
            'signature' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'credentials' => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
        ]);

        $platform = Platform::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'type' => $data['type'],
            'signature' => $data['signature'] ?? null,
            'secret_key' => $data['secret_key'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
            'credentials' => $data['credentials'] ?? [],
            'settings' => $data['settings'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Platform created successfully.',
            'data' => $this->serializePlatform($platform),
        ], 201);
    }

    public function show(Platform $platform): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->serializePlatform($platform),
        ]);
    }

    public function update(Request $request, Platform $platform): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('platforms', 'slug')->ignore($platform->id)],
            'type' => ['required', Rule::in(['hubspot', 'odoo', 'netsuite', 'generic'])],
            'signature' => ['nullable', 'string', 'max:255'],
            'secret_key' => ['nullable', 'string', 'max:255'],
            'active' => ['sometimes', 'boolean'],
            'credentials' => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
        ]);

        $platform->update([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'type' => $data['type'],
            'signature' => $data['signature'] ?? null,
            'secret_key' => $data['secret_key'] ?? null,
            'active' => (bool) ($data['active'] ?? false),
            'credentials' => $data['credentials'] ?? [],
            'settings' => $data['settings'] ?? [],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Platform updated successfully.',
            'data' => $this->serializePlatform($platform->fresh()),
        ]);
    }

    public function destroy(Platform $platform): JsonResponse
    {
        $platform->delete();

        return response()->json([
            'success' => true,
            'message' => 'Platform deleted successfully.',
        ]);
    }

    public function testConnection(Platform $platform, EventProcessingService $eventProcessingService): JsonResponse
    {
        $serviceClass = $eventProcessingService->getServiceClass($platform);

        if (! $serviceClass || ! class_exists($serviceClass)) {
            return response()->json([
                'success' => false,
                'message' => 'Platform service class not found.',
            ], 404);
        }

        $service = app()->make($serviceClass, [
            'platform' => $platform,
        ]);

        if (! method_exists($service, 'testConnection')) {
            return response()->json([
                'success' => false,
                'message' => 'Platform service does not support connection testing.',
            ], 400);
        }

        $result = $service->testConnection();

        if (! is_array($result)) {
            $result = [
                'success' => (bool) $result,
                'message' => $result ? 'Connection successful.' : 'Connection failed.',
            ];
        }

        return response()->json($result);
    }

    private function serializePlatform(Platform $platform): array
    {
        return [
            'id' => $platform->id,
            'name' => $platform->name,
            'slug' => $platform->slug,
            'type' => $platform->type,
            'signature' => $platform->signature,
            'active' => (bool) $platform->active,
            'has_secret_key' => ! empty($platform->secret_key),
            'credential_keys' => array_keys($platform->credentials ?? []),
            'settings' => $platform->settings ?? [],
        ];
    }
}
