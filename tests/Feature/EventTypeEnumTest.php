<?php

namespace Tests\Feature;

use App\Enums\EventType;
use App\Models\Event;
use App\Models\Platform;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTypeEnumTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_exposes_grouped_event_type_options(): void
    {
        $groups = EventType::groupedOptions();

        $this->assertNotEmpty($groups);
        $this->assertSame('Core Events', $groups[0]['label']);
        $this->assertTrue(collect($groups)->contains(
            fn (array $group): bool => collect($group['options'])->contains(
                fn (array $option): bool => $option['value'] === EventType::GENERIC_EXTERNAL_CALL->value
            )
        ));
    }

    public function test_it_filters_grouped_options_for_platform_type(): void
    {
        $groups = EventType::groupedOptions('generic');
        $options = collect($groups)->flatMap(fn (array $group) => $group['options']);

        $this->assertTrue($options->contains(fn (array $option): bool => $option['value'] === EventType::GENERIC_EXTERNAL_CALL->value));
        $this->assertFalse($options->contains(fn (array $option): bool => $option['value'] === EventType::ODOO_GET_LIST_PRICES->value));
    }

    public function test_it_includes_hubspot_subscription_based_property_changes_for_hubspot_platforms(): void
    {
        $groups = EventType::groupedOptions('hubspot');
        $options = collect($groups)->flatMap(fn (array $group) => $group['options']);

        $this->assertTrue($options->contains(fn (array $option): bool => $option['value'] === EventType::HUBSPOT_CONTACT_PROPERTY_CHANGE->value));
        $this->assertTrue($options->contains(fn (array $option): bool => $option['value'] === EventType::HUBSPOT_COMPANY_PROPERTY_CHANGE->value));
        $this->assertTrue($options->contains(fn (array $option): bool => $option['value'] === EventType::HUBSPOT_DEAL_PROPERTY_CHANGE->value));
        $this->assertTrue($options->contains(fn (array $option): bool => $option['value'] === EventType::HUBSPOT_OBJECT_PROPERTY_CHANGE->value));
        $this->assertFalse($options->contains(fn (array $option): bool => $option['value'] === EventType::ODOO_GET_STORE_PRODUCTS->value));
    }

    public function test_event_model_resolves_label_and_dispatch_class_from_enum(): void
    {
        $platform = Platform::query()->create([
            'name' => 'Generic',
            'slug' => 'generic-main',
            'type' => 'generic',
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Generic Call',
            'event_type_id' => EventType::GENERIC_EXTERNAL_CALL->value,
            'type' => 'webhook',
            'active' => true,
        ]);

        $this->assertSame('Generic External Call', $event->getEventTypeLabel());
        $this->assertSame(\App\Events\Generic\ExternalCallEvent::class, $event->getEventClass());
    }

    public function test_event_model_resolves_hubspot_method_name_from_subscription_type(): void
    {
        $platform = Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot-main',
            'type' => 'hubspot',
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Contact Property Change',
            'event_type_id' => EventType::HUBSPOT_CONTACT_PROPERTY_CHANGE->value,
            'type' => 'webhook',
            'subscription_type' => 'contact.propertyChange',
            'method_name' => null,
            'active' => true,
        ]);

        $this->assertSame('contactPropertyChange', $event->getMethodName());
    }

    public function test_event_model_resolves_hubspot_method_name_from_event_type_when_subscription_is_missing(): void
    {
        $platform = Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot-fallback',
            'type' => 'hubspot',
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Contact Property Change Fallback',
            'event_type_id' => EventType::HUBSPOT_CONTACT_PROPERTY_CHANGE->value,
            'type' => 'webhook',
            'subscription_type' => null,
            'method_name' => null,
            'active' => true,
        ]);

        $this->assertSame('contactPropertyChange', $event->getMethodName());
    }

    public function test_event_model_trims_subscription_type_before_method_resolution(): void
    {
        $platform = Platform::query()->create([
            'name' => 'HubSpot',
            'slug' => 'hubspot-trimmed',
            'type' => 'hubspot',
            'active' => true,
        ]);

        $event = Event::query()->create([
            'platform_id' => $platform->id,
            'name' => 'Contact Property Change Trim',
            'event_type_id' => EventType::HUBSPOT_CONTACT_PROPERTY_CHANGE->value,
            'type' => 'webhook',
            'subscription_type' => '  contact.propertyChange  ',
            'method_name' => null,
            'active' => true,
        ]);

        $this->assertSame('contactPropertyChange', $event->getMethodName());
    }
}
