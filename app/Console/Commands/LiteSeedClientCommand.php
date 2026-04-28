<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\PlatformConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class LiteSeedClientCommand extends Command
{
    protected $signature = 'lite:seed-client
        {slug : Client slug}
        {name : Client name}
        {--hubspot-token= : HubSpot access token}
        {--hubspot-secret= : HubSpot webhook secret}
        {--hubspot-base-url=https://api.hubapi.com : HubSpot base URL}
        {--hubspot-signature=x-signature : HubSpot signature header}
        {--trebel-base-url= : Trebel base URL}
        {--trebel-send-path=/messages/send : Trebel send path}
        {--trebel-auth-mode=bearer_api_key : Trebel auth mode}
        {--trebel-api-key= : Trebel API key}
        {--inactive-trebel : Create Trebel connection as inactive}';

    protected $description = 'Create or update a Lite client with HubSpot and optional Trebel connections';

    public function handle(): int
    {
        $slug = Str::slug((string) $this->argument('slug'));
        $name = (string) $this->argument('name');

        $client = Client::query()->updateOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'active' => true,
            ]
        );

        $hubspot = PlatformConnection::query()->firstOrNew([
            'client_id' => $client->id,
            'platform_type' => 'hubspot',
            'slug' => 'hubspot',
        ]);

        $hubspot->fill([
            'name' => 'HubSpot',
            'base_url' => (string) $this->option('hubspot-base-url'),
            'signature_header' => (string) $this->option('hubspot-signature'),
            'webhook_secret' => $this->filledOption('hubspot-secret')
                ? (string) $this->option('hubspot-secret')
                : $hubspot->webhook_secret,
            'credentials' => array_filter([
                'access_token' => $this->filledOption('hubspot-token')
                    ? (string) $this->option('hubspot-token')
                    : ($hubspot->credentials['access_token'] ?? null),
            ], static fn (mixed $value): bool => filled($value)),
            'settings' => array_merge($hubspot->settings ?? [], [
                'timeout_seconds' => 30,
                'contact_properties' => [
                    'firstname',
                    'lastname',
                    'phone',
                    'mobilephone',
                    'campus_de_interes',
                    'nivel_escolar_de_interes',
                    'plantilla_de_whatsapp',
                ],
            ]),
            'active' => true,
        ]);
        $hubspot->save();

        $trebel = PlatformConnection::query()->firstOrNew([
            'client_id' => $client->id,
            'platform_type' => 'trebel',
            'slug' => 'trebel',
        ]);

        $trebelCredentials = $trebel->credentials ?? [];
        if ($this->filledOption('trebel-api-key')) {
            $trebelCredentials['api_key'] = (string) $this->option('trebel-api-key');
        }

        $trebel->fill([
            'name' => 'Trebel',
            'base_url' => $this->filledOption('trebel-base-url')
                ? (string) $this->option('trebel-base-url')
                : ($trebel->base_url ?: null),
            'credentials' => $trebelCredentials,
            'settings' => array_merge($trebel->settings ?? [], [
                'send_path' => (string) $this->option('trebel-send-path'),
                'http_method' => 'POST',
                'auth_mode' => (string) $this->option('trebel-auth-mode'),
                'api_key_header' => 'X-API-Key',
                'timeout_seconds' => 20,
                'headers' => [],
                'request_template' => [
                    'template_id' => '{{template.external_template_id}}',
                    'phone' => '{{contact.phone}}',
                    'first_name' => '{{contact.firstname}}',
                    'last_name' => '{{contact.lastname}}',
                    'campus' => '{{contact.campus_de_interes}}',
                    'school_level' => '{{contact.nivel_escolar_de_interes}}',
                ],
            ]),
            'active' => ! (bool) $this->option('inactive-trebel'),
        ]);
        $trebel->save();

        $this->info("Client [{$client->slug}] is ready.");
        $this->line("HubSpot connection id: {$hubspot->id}");
        $this->line("Trebel connection id: {$trebel->id}");

        return self::SUCCESS;
    }

    private function filledOption(string $name): bool
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '';
    }
}
