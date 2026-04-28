<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\MessageRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MessageRuleManagementController extends Controller
{
    public function store(Request $request, Client $client): RedirectResponse
    {
        $data = $this->validatePayload($request, $client);

        MessageRule::query()->create([
            'client_id' => $client->id,
            'trebel_template_id' => $data['trebel_template_id'],
            'name' => $data['name'],
            'priority' => (int) ($data['priority'] ?? 100),
            'trigger_property' => $data['trigger_property'],
            'trigger_value' => $data['trigger_value'] ?? null,
            'conditions' => $data['conditions'] ?? [],
            'active' => (bool) ($data['active'] ?? true),
        ]);

        return redirect()->route('admin.clients.rules', $client)->with('success', 'Regla creada correctamente.');
    }

    public function update(Request $request, Client $client, MessageRule $rule): RedirectResponse
    {
        abort_unless($rule->client_id === $client->id, 404);
        $data = $this->validatePayload($request, $client);

        $rule->update([
            'trebel_template_id' => $data['trebel_template_id'],
            'name' => $data['name'],
            'priority' => (int) ($data['priority'] ?? 100),
            'trigger_property' => $data['trigger_property'],
            'trigger_value' => $data['trigger_value'] ?? null,
            'conditions' => $data['conditions'] ?? [],
            'active' => (bool) ($data['active'] ?? false),
        ]);

        return redirect()->route('admin.clients.rules', $client)->with('success', 'Regla actualizada correctamente.');
    }

    public function destroy(Client $client, MessageRule $rule): RedirectResponse
    {
        abort_unless($rule->client_id === $client->id, 404);
        $rule->delete();

        return redirect()->route('admin.clients.rules', $client)->with('success', 'Regla eliminada correctamente.');
    }

    private function validatePayload(Request $request, Client $client): array
    {
        return $request->validate([
            'trebel_template_id' => [
                'required',
                Rule::exists('trebel_templates', 'id')->where(fn ($query) => $query->where('client_id', $client->id)),
            ],
            'name' => ['required', 'string', 'max:255'],
            'priority' => ['nullable', 'integer'],
            'trigger_property' => ['required', 'string', 'max:255'],
            'trigger_value' => ['nullable', 'string', 'max:255'],
            'conditions' => ['sometimes', 'array'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }
}
