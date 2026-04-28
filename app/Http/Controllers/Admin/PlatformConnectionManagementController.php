<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PlatformConnection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlatformConnectionManagementController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validatePayload($request, $client);

        PlatformConnection::query()->create($this->buildAttributes($client, $data));

        return redirect()->route('admin.clients.connections', $client)->with('success', 'Conexión creada correctamente.');
    }

    public function update(Request $request, Client $client, PlatformConnection $connection): RedirectResponse
    {
        abort_unless($connection->client_id === $client->id, 404);

        $data = $this->validatePayload($request, $client, $connection);
        $connection->update($this->buildAttributes($client, $data, $connection));

        return redirect()->route('admin.clients.connections', $client)->with('success', 'Conexión actualizada correctamente.');
    }

    public function destroy(Client $client, PlatformConnection $connection): RedirectResponse
    {
        abort_unless($connection->client_id === $client->id, 404);
        $connection->delete();

        return redirect()->route('admin.clients.connections', $client)->with('success', 'Conexión eliminada correctamente.');
    }

    private function validatePayload(Request $request, Client $client, ?PlatformConnection $connection = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('platform_connections', 'slug')
                    ->where(fn ($query) => $query->where('client_id', $client->id))
                    ->ignore($connection?->id),
            ],
            'platform_type' => ['required', Rule::in(['hubspot', 'trebel'])],
            'base_url' => ['nullable', 'url'],
            'signature_header' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'credentials' => ['sometimes', 'array'],
            'settings' => ['sometimes', 'array'],
        ]);

        $credentials = is_array($data['credentials'] ?? null) ? $data['credentials'] : [];
        $settings = is_array($data['settings'] ?? null) ? $data['settings'] : [];
        $errors = [];

        if ($data['platform_type'] === 'hubspot') {
            $hasToken = filled($credentials['access_token'] ?? null)
                || filled($connection?->credentials['access_token'] ?? null);

            if (! $hasToken) {
                $errors['credentials.access_token'] = 'HubSpot requiere access token.';
            }
        }

        if ($data['platform_type'] === 'trebel') {
            if (! filled($data['base_url'] ?? null) && ! filled($connection?->base_url ?? null)) {
                $errors['base_url'] = 'Trebel requiere base URL.';
            }

            if (! filled($settings['send_path'] ?? null) && ! filled($connection?->settings['send_path'] ?? null)) {
                $errors['settings.send_path'] = 'Trebel requiere send path.';
            }

            $authMode = (string) ($settings['auth_mode'] ?? $connection?->settings['auth_mode'] ?? '');
            if ($authMode === 'bearer_api_key' || $authMode === 'header_api_key') {
                $hasApiKey = filled($credentials['api_key'] ?? null)
                    || filled($connection?->credentials['api_key'] ?? null);

                if (! $hasApiKey) {
                    $errors['credentials.api_key'] = 'Trebel requiere API key para el modo de autenticación seleccionado.';
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $data;
    }

    private function buildAttributes(Client $client, array $data, ?PlatformConnection $connection = null): array
    {
        $credentials = $connection?->credentials ?? [];
        foreach (($data['credentials'] ?? []) as $key => $value) {
            if (is_string($value) && trim($value) === '') {
                continue;
            }

            $credentials[$key] = $value;
        }

        $settings = array_merge($connection?->settings ?? [], Arr::wrap($data['settings'] ?? []));

        return [
            'client_id' => $client->id,
            'platform_type' => $data['platform_type'],
            'name' => $data['name'],
            'slug' => $data['slug'] ?? Str::slug($data['name']),
            'base_url' => $data['base_url'] ?? null,
            'signature_header' => $data['signature_header'] ?? null,
            'webhook_secret' => filled($data['webhook_secret'] ?? null)
                ? $data['webhook_secret']
                : $connection?->webhook_secret,
            'active' => (bool) ($data['active'] ?? true),
            'credentials' => $credentials,
            'settings' => $settings,
        ];
    }
}
