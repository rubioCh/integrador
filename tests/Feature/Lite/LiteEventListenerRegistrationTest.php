<?php

namespace Tests\Feature\Lite;

use App\Events\HubSpot\ContactPropertyChangedEvent;
use App\Listeners\HubSpot\ContactPropertyChangedListener;
use App\Models\Client;
use App\Models\PlatformConnection;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use Tests\TestCase;

class LiteEventListenerRegistrationTest extends TestCase
{
    public function test_contact_property_changed_listener_is_registered_once(): void
    {
        /** @var Dispatcher $dispatcher */
        $dispatcher = app('events');

        $reflection = new ReflectionClass($dispatcher);
        $property = $reflection->getProperty('listeners');
        $property->setAccessible(true);
        $listeners = $property->getValue($dispatcher);

        $registered = $listeners[ContactPropertyChangedEvent::class] ?? [];

        $this->assertSame([ContactPropertyChangedListener::class], $registered);
        $this->assertCount(1, $dispatcher->getListeners(ContactPropertyChangedEvent::class));
    }

    public function test_dispatching_contact_property_changed_event_enqueues_single_listener_job(): void
    {
        Queue::fake();

        $client = new Client([
            'id' => 1,
            'name' => 'Acme',
            'slug' => 'acme',
            'active' => true,
        ]);

        $connection = new PlatformConnection([
            'id' => 2,
            'client_id' => 1,
            'platform_type' => 'hubspot',
            'name' => 'HubSpot',
            'slug' => 'hubspot',
            'active' => true,
        ]);

        event(new ContactPropertyChangedEvent($client, $connection, [
            'subscriptionType' => 'contact.propertyChange',
            'objectId' => '123',
            'propertyName' => 'plantilla_de_whatsapp',
            'propertyValue' => 'Bienvenida',
        ]));

        Queue::assertPushed(CallQueuedListener::class, 1);
    }
}
