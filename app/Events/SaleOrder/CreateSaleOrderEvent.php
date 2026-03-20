<?php

namespace App\Events\SaleOrder;

use App\Events\BaseEvent;

class CreateSaleOrderEvent extends BaseEvent
{
    public function getEventType(): string
    {
        return 'sale_order.created';
    }

    public function getEventDescription(): string
    {
        return 'Sale order creation event';
    }
}
