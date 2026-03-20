<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Platform;
use App\Models\Property;
use App\Services\Hubspot\HubspotFilePropertyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HubspotFilePropertyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_downloads_and_attaches_file_properties(): void
    {
        Http::fake([
            'https://files.example.com/*' => Http::response('file-content', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $platform = Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot',
            'type' => 'hubspot',
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'File Event',
            'event_type_id' => 'object.updated',
            'type' => 'webhook',
            'subscription_type' => 'object.propertyChange',
            'active' => true,
        ]);

        $property = Property::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Attachment',
            'key' => 'document_url',
            'type' => 'file',
            'active' => true,
        ]);

        $event->properties()->sync([$property->id]);

        $service = app(HubspotFilePropertyService::class);
        $result = $service->hydrateFileProperties($event, [
            'document_url' => 'https://files.example.com/file-1.pdf',
        ]);

        $this->assertArrayHasKey('_file_attachments', $result);
        $this->assertArrayHasKey('document_url', $result['_file_attachments']);
        $this->assertSame('application/pdf', $result['_file_attachments']['document_url']['mime_type']);
        $this->assertNotEmpty($result['_file_attachments']['document_url']['content_base64']);
    }
}
