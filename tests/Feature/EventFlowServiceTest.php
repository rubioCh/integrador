<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Platform;
use App\Services\EventFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeFlowPlatformService;
use Tests\TestCase;

class EventFlowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_event_flow_uses_upstream_root_and_forward_chain(): void
    {
        $platform = Platform::query()->create([
            'name' => 'Generic Platform',
            'slug' => 'generic-platform',
            'type' => 'generic',
            'active' => true,
        ]);

        $root = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Root Event',
            'event_type_id' => 'generic.external.call',
            'type' => 'webhook',
            'method_name' => 'firstStep',
            'active' => true,
        ]);

        $middle = Event::query()->create([
            'platform_id' => $platform->id,
            'to_event_id' => null,
            'name' => 'Middle Event',
            'event_type_id' => 'generic.external.call',
            'type' => 'webhook',
            'method_name' => 'secondStep',
            'active' => true,
        ]);

        $tail = Event::query()->create([
            'platform_id' => $platform->id,
            'to_event_id' => null,
            'name' => 'Tail Event',
            'event_type_id' => 'generic.external.call',
            'type' => 'webhook',
            'method_name' => 'secondStep',
            'active' => true,
        ]);

        $root->update(['to_event_id' => $middle->id]);
        $middle->update(['to_event_id' => $tail->id]);

        $service = app(EventFlowService::class);
        $flow = $service->getEventFlow($middle);

        $this->assertSame($root->id, $flow['root_id']);
        $this->assertSame([$root->id, $middle->id, $tail->id], $flow['chain']);
        $this->assertCount(3, $flow['nodes']);
        $this->assertSame([
            ['from' => $root->id, 'to' => $middle->id],
            ['from' => $middle->id, 'to' => $tail->id],
        ], $flow['edges']);
    }

    public function test_execute_event_flow_runs_chain_from_root_even_if_middle_is_provided(): void
    {
        FakeFlowPlatformService::reset();

        $originalPlatformClassList = app()->bound('platformClassList')
            ? app('platformClassList')
            : [];
        app()->instance('platformClassList', array_merge($originalPlatformClassList, [
            'generic' => FakeFlowPlatformService::class,
        ]));

        $platform = Platform::query()->create([
            'name' => 'Generic Platform',
            'slug' => 'generic-platform',
            'type' => 'generic',
            'active' => true,
        ]);

        $root = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Root Event',
            'event_type_id' => 'generic.external.call',
            'type' => 'webhook',
            'method_name' => 'firstStep',
            'active' => true,
        ]);

        $next = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Next Event',
            'event_type_id' => 'generic.external.call',
            'type' => 'webhook',
            'method_name' => 'secondStep',
            'active' => true,
        ]);

        $root->update(['to_event_id' => $next->id]);

        $service = app(EventFlowService::class);
        $result = $service->executeEventFlow($next, ['payload' => ['value' => 10]]);

        $this->assertTrue($result['success']);
        $this->assertSame(['firstStep', 'secondStep'], FakeFlowPlatformService::$calls);
        $this->assertCount(2, $result['executed_events']);
        $this->assertSame($root->id, $result['executed_events'][0]['event_id']);
        $this->assertSame($next->id, $result['executed_events'][1]['event_id']);

        app()->instance('platformClassList', $originalPlatformClassList);
    }
}
