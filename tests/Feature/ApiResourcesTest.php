<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Platform;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_events_api_resource_crud_works_with_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'email' => 'events-api@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->grantPermission($user, 'events.manage');

        $platform = Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot',
            'type' => 'hubspot',
            'active' => true,
        ]);

        $headers = $this->basicAuth('events-api@example.com', 'password');

        $create = $this->postJson('/api/events', [
            'platform_id' => $platform->id,
            'name' => 'Deal Created',
            'event_type_id' => 'deal.created',
            'type' => 'webhook',
            'subscription_type' => 'deal.created',
            'method_name' => 'companyCreatedWebhook',
            'active' => true,
            'payload_mapping' => [],
            'meta' => [],
        ], $headers);

        $create->assertStatus(201)->assertJson(['success' => true]);
        $eventId = (int) $create->json('data.id');

        $this->getJson('/api/events', $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->getJson('/api/events/' . $eventId, $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $eventId);

        $this->putJson('/api/events/' . $eventId, [
            'platform_id' => $platform->id,
            'name' => 'Deal Created Updated',
            'event_type_id' => 'deal.created',
            'type' => 'webhook',
            'subscription_type' => 'deal.created',
            'method_name' => 'companyCreatedWebhook',
            'active' => false,
            'payload_mapping' => [],
            'meta' => [],
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Deal Created Updated');

        $this->deleteJson('/api/events/' . $eventId, [], $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_platforms_api_resource_crud_works_with_permission(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'email' => 'platforms-api@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->grantPermission($user, 'platforms.manage');

        $headers = $this->basicAuth('platforms-api@example.com', 'password');

        $create = $this->postJson('/api/platforms', [
            'name' => 'Generic API',
            'slug' => 'generic-api',
            'type' => 'generic',
            'active' => true,
            'credentials' => ['api_key' => 'secret'],
            'settings' => ['base_url' => 'https://api.example.com'],
        ], $headers);

        $create->assertStatus(201)->assertJson(['success' => true]);
        $platformId = (int) $create->json('data.id');

        $this->getJson('/api/platforms', $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->getJson('/api/platforms/' . $platformId, $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.id', $platformId);

        $this->putJson('/api/platforms/' . $platformId, [
            'name' => 'Generic API Updated',
            'slug' => 'generic-api-updated',
            'type' => 'generic',
            'active' => false,
            'credentials' => ['api_key' => 'secret'],
            'settings' => ['base_url' => 'https://api.updated.example.com'],
        ], $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Generic API Updated');

        $this->deleteJson('/api/platforms/' . $platformId, [], $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    /**
     * @return array<string,string>
     */
    private function basicAuth(string $email, string $password): array
    {
        return [
            'Authorization' => 'Basic ' . base64_encode($email . ':' . $password),
        ];
    }

    private function grantPermission(User $user, string $permissionSlug): void
    {
        $role = Role::query()->create([
            'name' => 'API CRUD Role ' . $permissionSlug,
            'slug' => 'api-crud-role-' . str_replace('.', '-', $permissionSlug) . '-' . $user->id,
            'description' => 'Temporary API role for tests',
        ]);

        $permission = Permission::query()->where('slug', $permissionSlug)->firstOrFail();
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}

