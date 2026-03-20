<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Platform;
use App\Services\EventTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTriggerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_true_when_event_has_no_triggers(): void
    {
        $event = $this->createEvent();
        $service = app(EventTriggerService::class);

        $result = $service->evaluateEventTriggers($event, ['any' => 'payload']);

        $this->assertTrue($result);
    }

    public function test_it_evaluates_groups_with_and_between_groups(): void
    {
        $event = $this->createEvent();
        $service = app(EventTriggerService::class);

        $service->syncEventTriggers($event, [
            [
                'name' => 'Commercial',
                'operator' => 'or',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'won'],
                    ['field' => 'amount', 'operator' => 'greater_than', 'value' => 1000],
                ],
            ],
            [
                'name' => 'Territory',
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'customer.country', 'operator' => 'equals', 'value' => 'US'],
                ],
            ],
        ]);

        $this->assertTrue($service->evaluateEventTriggers($event, [
            'status' => 'open',
            'amount' => 1500,
            'customer' => ['country' => 'US'],
        ]));

        $this->assertFalse($service->evaluateEventTriggers($event, [
            'status' => 'open',
            'amount' => 900,
            'customer' => ['country' => 'US'],
        ]));

        $this->assertFalse($service->evaluateEventTriggers($event, [
            'status' => 'won',
            'amount' => 900,
            'customer' => ['country' => 'CA'],
        ]));
    }

    public function test_it_replaces_groups_when_syncing_new_configuration(): void
    {
        $event = $this->createEvent();
        $service = app(EventTriggerService::class);

        $service->syncEventTriggers($event, [
            [
                'name' => 'Initial',
                'operator' => 'and',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'open'],
                ],
            ],
        ]);

        $service->syncEventTriggers($event, [
            [
                'name' => 'Updated',
                'operator' => 'or',
                'conditions' => [
                    ['field' => 'status', 'operator' => 'equals', 'value' => 'won'],
                ],
            ],
        ]);

        $payload = $service->getEventTriggers($event->fresh());

        $this->assertCount(1, $payload['groups']);
        $this->assertSame('Updated', $payload['groups'][0]['name']);
        $this->assertSame('or', $payload['groups'][0]['operator']);
    }

    private function createEvent(): Event
    {
        $platform = Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot',
            'type' => 'hubspot',
            'active' => true,
        ]);

        return Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Deal Updated',
            'event_type_id' => 'object.updated',
            'type' => 'webhook',
            'subscription_type' => 'deal.propertyChange',
            'method_name' => 'objectPropertyChange',
            'active' => true,
        ]);
    }
}
