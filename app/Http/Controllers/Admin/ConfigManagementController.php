<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Config;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConfigManagementController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255', 'unique:configs,key'],
            'value' => ['nullable', 'array'],
            'description' => ['nullable', 'string'],
            'is_encrypted' => ['sometimes', 'boolean'],
        ]);

        Config::query()->create($data);

        return redirect()->route('admin.configs')->with('success', 'Configuración creada correctamente.');
    }

    public function update(Request $request, Config $config): RedirectResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'max:255', Rule::unique('configs', 'key')->ignore($config->id)],
            'value' => ['nullable', 'array'],
            'description' => ['nullable', 'string'],
            'is_encrypted' => ['sometimes', 'boolean'],
        ]);

        $config->update($data);

        return redirect()->route('admin.configs')->with('success', 'Configuración actualizada correctamente.');
    }

    public function destroy(Config $config): RedirectResponse
    {
        $config->delete();

        return redirect()->route('admin.configs')->with('success', 'Configuración eliminada correctamente.');
    }
}
