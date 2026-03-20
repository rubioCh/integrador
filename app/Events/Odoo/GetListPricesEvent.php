<?php

namespace App\Events\Odoo;

use App\Events\BaseEvent;

class GetListPricesEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'odoo.get_list_prices';
    }

    public function getEventDescription(): string
    {
        return 'Odoo list prices fetch event';
    }
}
