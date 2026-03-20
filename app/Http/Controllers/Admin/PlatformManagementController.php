<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Platform;
use App\Services\EventProcessingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlatformManagementController extends Controller
{
    public function store(Request $request): RedirectResponse
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

        Platform::query()->create([
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'type' => $data['type'],
            'signature' => $data['signature'] ?? null,
            'secret_key' => $data['secret_key'] ?? null,
            'active' => (bool) ($data['active'] ?? true),
            'credentials' => $data['credentials'] ?? [],
            'settings' => $data['settings'] ?? [],
        ]);

        return redirect()->route('admin.platforms')->with('success', 'Plataforma creada correctamente.');
    }

    public function update(Request $request, Platform $platform): RedirectResponse
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

        return redirect()->route('admin.platforms')->with('success', 'Plataforma actualizada correctamente.');
    }

    public function destroy(Platform $platform): RedirectResponse
    {
        $platform->delete();

        return redirect()->route('admin.platforms')->with('success', 'Plataforma eliminada correctamente.');
    }

    public function testConnection(Platform $platform, EventProcessingService $eventProcessingService): RedirectResponse
    {
        $serviceClass = $eventProcessingService->getServiceClass($platform);

        if (! $serviceClass || ! class_exists($serviceClass)) {
            return redirect()
                ->route('admin.platforms')
                ->with('error', 'No se encontró servicio para probar la conexión de esta plataforma.');
        }

        $service = app()->make($serviceClass, [
            'platform' => $platform,
        ]);

        if (! method_exists($service, 'testConnection')) {
            return redirect()
                ->route('admin.platforms')
                ->with('error', 'El servicio de plataforma no implementa test de conexión.');
        }

        try {
            $result = $service->testConnection();
        } catch (\Throwable $exception) {
            return redirect()
                ->route('admin.platforms')
                ->with('error', 'Error al probar conexión: ' . $exception->getMessage());
        }

        $success = (bool) ($result['success'] ?? false);
        $message = (string) ($result['message'] ?? ($success ? 'Conexión validada.' : 'Conexión fallida.'));
        $channel = $success ? 'success' : 'error';

        return redirect()
            ->route('admin.platforms')
            ->with($channel, "[{$platform->name}] {$message}");
    }
}
