<?php

namespace Tests\Unit;

use App\Models\Event;
use App\Models\Platform;
use App\Models\Property;
use App\Models\Record;
use PHPUnit\Framework\TestCase;

class ModelsRelationshipTest extends TestCase
{
    public function test_event_model_has_expected_relationship_methods(): void
    {
        $event = new Event();

        $this->assertTrue(method_exists($event, 'platform'));
        $this->assertTrue(method_exists($event, 'to_event'));
        $this->assertTrue(method_exists($event, 'from_events'));
        $this->assertTrue(method_exists($event, 'records'));
        $this->assertTrue(method_exists($event, 'properties'));
    }

    public function test_record_model_has_hierarchy_relationship_methods(): void
    {
        $record = new Record();

        $this->assertTrue(method_exists($record, 'parent'));
        $this->assertTrue(method_exists($record, 'childrens'));
        $this->assertTrue(method_exists($record, 'event'));
    }

    public function test_platform_and_property_models_expose_expected_relationships(): void
    {
        $platform = new Platform();
        $property = new Property();

        $this->assertTrue(method_exists($platform, 'events'));
        $this->assertTrue(method_exists($platform, 'properties'));
        $this->assertTrue(method_exists($property, 'platform'));
        $this->assertTrue(method_exists($property, 'events'));
        $this->assertTrue(method_exists($property, 'categories'));
    }
}
