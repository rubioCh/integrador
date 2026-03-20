<?php

namespace App\Events\Odoo;

use App\Events\BaseEvent;

class GetStoreProductsEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'odoo.get_store_products';
    }

    public function getEventDescription(): string
    {
        return 'Odoo store products fetch event';
    }
}
