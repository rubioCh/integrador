<?php

namespace App\Events\Product;

use App\Events\BaseEvent;

class CreateProductEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'product.created';
    }

    public function getEventDescription(): string
    {
        return 'Product creation event';
    }
}
