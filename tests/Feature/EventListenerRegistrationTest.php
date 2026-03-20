<?php

namespace Tests\Feature;

use App\Events\Object\UpdateObjectEvent;
use App\Listeners\Object\UpdateObjectListener;
use App\Models\Event;
use App\Models\Record;
use Illuminate\Events\Dispatcher;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use Tests\TestCase;

class EventListenerRegistrationTest extends TestCase
{
    public function test_app_event_listener_is_registered_once_without_handle_duplicate(): void
    {
        /** @var Dispatcher $dispatcher */
        $dispatcher = app('events');

        $reflection = new ReflectionClass($dispatcher);
        $property = $reflection->getProperty('listeners');
        $property->setAccessible(true);
        $listeners = $property->getValue($dispatcher);

        $registered = $listeners[UpdateObjectEvent::class] ?? [];

        $this->assertSame([UpdateObjectListener::class], $registered);
        $this->assertCount(1, $dispatcher->getListeners(UpdateObjectEvent::class));
    }

    public function test_dispatching_app_event_enqueues_single_listener_job(): void
    {
        Queue::fake();

        $eventModel = new Event([
            'id' => 999999,
            'name' => 'Object Update Test',
            'event_type_id' => 'object.updated',
        ]);
        $record = new Record([
            'id' => 888888,
            'event_type' => 'object.updated',
            'status' => 'init',
            'payload' => [],
            'message' => 'Test record',
        ]);

        event(new UpdateObjectEvent($eventModel, $record, ['objectId' => 401]));

        Queue::assertPushed(CallQueuedListener::class, 1);
    }
}
