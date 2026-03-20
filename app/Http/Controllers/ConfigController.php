<?php

namespace App\Http\Controllers;

use App\Models\Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function index(): JsonResponse
    {
        $configs = Config::query()
            ->orderBy('key')
            ->paginate(50)
            ->through(fn (Config $config): array => $this->transformConfig($config));

        return response()->json($configs);
    }

    public function show(Config $config): JsonResponse
    {
        return response()->json($this->transformConfig($config));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255', 'unique:configs,key'],
            'value' => ['nullable', 'array'],
            'description' => ['nullable', 'string'],
            'is_encrypted' => ['sometimes', 'boolean'],
        ]);

        $config = Config::query()->create($data);

        return response()->json($this->transformConfig($config), 201);
    }

    public function update(Request $request, Config $config): JsonResponse
    {
        $data = $request->validate([
            'key' => ['sometimes', 'string', 'max:255', 'unique:configs,key,' . $config->id],
            'value' => ['sometimes', 'nullable', 'array'],
            'description' => ['sometimes', 'nullable', 'string'],
            'is_encrypted' => ['sometimes', 'boolean'],
        ]);

        $config->update($data);

        return response()->json($this->transformConfig($config));
    }

    public function destroy(Config $config): JsonResponse
    {
        $config->delete();

        return response()->json(['success' => true]);
    }

    private function transformConfig(Config $config): array
    {
        return [
            'id' => $config->id,
            'key' => $config->key,
            'value' => $this->sanitizeValue($config),
            'description' => $config->description,
            'is_encrypted' => (bool) $config->is_encrypted,
            'created_at' => optional($config->created_at)?->toISOString(),
            'updated_at' => optional($config->updated_at)?->toISOString(),
        ];
    }

    private function sanitizeValue(Config $config): mixed
    {
        if ($config->is_encrypted || $this->isSensitiveKey($config->key)) {
            return '[redacted]';
        }

        return $config->value;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);

        foreach (['secret', 'token', 'password', 'private_key', 'client_secret'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}
