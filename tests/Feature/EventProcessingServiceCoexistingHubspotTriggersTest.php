<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Platform;
use App\Services\EventProcessingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class EventProcessingServiceCoexistingHubspotTriggersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_processes_event_type_and_subscription_type_candidates_without_dropping_one(): void
    {
        EventFacade::fake();

        $platform = Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot',
            'type' => 'hubspot',
            'active' => true,
        ]);

        $eventByType = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Create from Lifecycle Stage',
            'event_type_id' => 'contact.propertyChange',
            'type' => 'webhook',
            'active' => true,
        ]);

        $eventBySubscription = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Manual Sync to ASPEL',
            'event_type_id' => 'contact.propertyChange',
            'subscription_type' => 'contact.propertyChange',
            'type' => 'webhook',
            'active' => true,
        ]);

        app(\App\Services\EventTriggerService::class)->syncEventTriggers($eventByType, [[
            'name' => 'Lifecycle opportunity',
            'operator' => 'and',
            'active' => true,
            'conditions' => [
                ['field' => 'propertyName', 'operator' => 'equals', 'value' => 'lifecyclestage'],
                ['field' => 'propertyValue', 'operator' => 'equals', 'value' => 'opportunity'],
            ],
        ]]);

        app(\App\Services\EventTriggerService::class)->syncEventTriggers($eventBySubscription, [[
            'name' => 'Manual sync pending',
            'operator' => 'and',
            'active' => true,
            'conditions' => [
                ['field' => 'propertyName', 'operator' => 'equals', 'value' => 'sync_to_aspel'],
                ['field' => 'propertyValue', 'operator' => 'equals', 'value' => 'pending'],
            ],
        ]]);

        $result = app(EventProcessingService::class)->processEvent('contact.propertyChange', [
            'subscriptionType' => 'contact.propertyChange',
            'objectId' => 208589143093,
            'propertyName' => 'lifecyclestage',
            'propertyValue' => 'opportunity',
        ], $platform);

        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['processed_events']);

        $processed = collect($result['processed_events'])->keyBy('event_id');

        $this->assertTrue($processed[$eventByType->id]['success']);
        $this->assertFalse((bool) ($processed[$eventByType->id]['skipped'] ?? false));
        $this->assertTrue($processed[$eventBySubscription->id]['success']);
        $this->assertTrue((bool) ($processed[$eventBySubscription->id]['skipped'] ?? false));
        $this->assertSame('Trigger conditions not met', $processed[$eventBySubscription->id]['message']);
    }
}
