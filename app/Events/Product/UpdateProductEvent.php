<?php

namespace App\Events\Product;

use App\Events\BaseEvent;

class UpdateProductEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'product.updated';
    }

    public function getEventDescription(): string
    {
        return 'Product update event';
    }
}
