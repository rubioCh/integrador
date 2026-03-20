<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ExecuteEventJob;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EventManagementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatePayload($request);
        $this->assertAllowedBaseUrl(Arr::get($data, 'http_config.base_url'));

        $event = Event::query()->create($this->extractEventData($data) + [
            'active' => (bool) ($data['active'] ?? true),
        ]);
        $this->syncHttpConfig($event, Arr::get($data, 'http_config'));

        return redirect()->route('admin.events')->with('success', 'Evento creado correctamente.');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $data = $this->validatePayload($request, $event);
        $this->assertAllowedBaseUrl(Arr::get($data, 'http_config.base_url'));

        $event->update($this->extractEventData($data) + [
            'active' => (bool) ($data['active'] ?? false),
        ]);
        $this->syncHttpConfig($event, Arr::get($data, 'http_config'));

        return redirect()->route('admin.events')->with('success', 'Evento actualizado correctamente.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return redirect()->route('admin.events')->with('success', 'Evento eliminado correctamente.');
    }

    public function executeNow(Event $event): RedirectResponse
    {
        if ($event->type !== 'schedule') {
            return redirect()
                ->route('admin.events')
                ->with('error', 'Solo los eventos tipo schedule se pueden ejecutar manualmente.');
        }

        if (! $event->active) {
            return redirect()
                ->route('admin.events')
                ->with('error', 'El evento debe estar activo para ejecutar manualmente.');
        }

        ExecuteEventJob::dispatch($event)->onQueue('events');

        return redirect()
            ->route('admin.events')
            ->with('success', "Evento '{$event->name}' encolado para ejecución.");
    }

    private function validatePayload(Request $request, ?Event $event = null): array
    {
        $eventId = $event?->id;

        return $request->validate([
            'platform_id' => ['required', 'integer', 'exists:platforms,id'],
            'to_event_id' => ['nullable', 'integer', 'exists:events,id', Rule::notIn([$eventId])],
            'name' => ['required', 'string', 'max:255'],
            'event_type_id' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['webhook', 'schedule'])],
            'subscription_type' => ['nullable', 'string', 'max:255'],
            'method_name' => ['nullable', 'string', 'max:255'],
            'endpoint_api' => ['nullable', 'string', 'max:255'],
            'schedule_expression' => ['nullable', 'required_if:type,schedule', 'string', 'max:255'],
            'command_sql' => ['nullable', 'string'],
            'enable_update_hubdb' => ['sometimes', 'boolean'],
            'hubdb_table_id' => ['nullable', 'integer', 'required_if:enable_update_hubdb,1'],
            'active' => ['sometimes', 'boolean'],
            'payload_mapping' => ['sometimes', 'array'],
            'meta' => ['sometimes', 'array'],
            'http_config' => ['nullable', 'array'],
            'http_config.active' => ['sometimes', 'boolean'],
            'http_config.method' => ['nullable', Rule::in(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'])],
            'http_config.base_url' => ['nullable', 'url', 'max:255'],
            'http_config.path' => ['nullable', 'string', 'max:255'],
            'http_config.headers_json' => ['nullable', 'array'],
            'http_config.query_json' => ['nullable', 'array'],
            'http_config.auth_mode' => ['nullable', Rule::in(['bearer_api_key', 'basic_auth', 'oauth2_client_credentials'])],
            'http_config.auth_config_json' => ['nullable', 'array'],
            'http_config.timeout_seconds' => ['nullable', 'integer', 'between:1,120'],
            'http_config.retry_policy_json' => ['nullable', 'array'],
            'http_config.idempotency_config_json' => ['nullable', 'array'],
            'http_config.allowlist_domains_json' => ['nullable', 'array'],
        ]);
    }

    private function extractEventData(array $data): array
    {
        return Arr::except($data, ['http_config']);
    }

    private function syncHttpConfig(Event $event, mixed $httpConfig): void
    {
        if (! is_array($httpConfig) || ! $this->hasHttpConfigData($httpConfig)) {
            $event->httpConfig()->delete();
            return;
        }

        $event->httpConfig()->updateOrCreate(
            ['event_id' => $event->id],
            [
                'method' => strtoupper((string) ($httpConfig['method'] ?? 'POST')),
                'base_url' => $httpConfig['base_url'] ?? null,
                'path' => $httpConfig['path'] ?? null,
                'headers_json' => $httpConfig['headers_json'] ?? [],
                'query_json' => $httpConfig['query_json'] ?? [],
                'auth_mode' => $httpConfig['auth_mode'] ?? null,
                'auth_config_json' => $httpConfig['auth_config_json'] ?? [],
                'timeout_seconds' => (int) ($httpConfig['timeout_seconds'] ?? 30),
                'retry_policy_json' => $httpConfig['retry_policy_json'] ?? [],
                'idempotency_config_json' => $httpConfig['idempotency_config_json'] ?? [],
                'allowlist_domains_json' => $httpConfig['allowlist_domains_json'] ?? [],
                'active' => (bool) ($httpConfig['active'] ?? true),
            ]
        );
    }

    private function hasHttpConfigData(array $httpConfig): bool
    {
        foreach ([
            'base_url',
            'path',
            'headers_json',
            'query_json',
            'auth_mode',
            'auth_config_json',
            'retry_policy_json',
            'idempotency_config_json',
            'allowlist_domains_json',
        ] as $key) {
            $value = $httpConfig[$key] ?? null;
            if (is_array($value) && ! empty($value)) {
                return true;
            }
            if (is_string($value) && trim($value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function assertAllowedBaseUrl(?string $baseUrl): void
    {
        if (! $baseUrl) {
            return;
        }

        $allowlist = config('generic-platforms.policy.allowed_domains', []);
        if (empty($allowlist)) {
            return;
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);
        if (! $host) {
            throw ValidationException::withMessages([
                'http_config.base_url' => 'La URL base no es válida.',
            ]);
        }

        foreach ($allowlist as $domain) {
            $domain = strtolower(trim((string) $domain));
            if ($domain === '') {
                continue;
            }

            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'http_config.base_url' => 'El dominio no está permitido por la allowlist configurada.',
        ]);
    }
}
