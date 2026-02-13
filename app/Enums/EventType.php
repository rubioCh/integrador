<?php

namespace App\Enums;

enum EventType: string
{
    case COMPANY_CREATED = 'company.created';
    case COMPANY_UPDATED = 'company.updated';
    case PRODUCT_CREATED = 'product.created';
    case PRODUCT_UPDATED = 'product.updated';
    case INVOICE_CREATED = 'invoice.created';
    case INVOICE_RECURRING_CREATED = 'invoice.recurring.created';
    case SALE_ORDER_CREATED = 'sale_order.created';
    case QUOTES_SENDING_DATA = 'quotes.sending_data';
    case RESPONSE_SEND = 'response.send';
    case OBJECT_UPDATED = 'object.updated';
    case ODOO_GET_LIST_PRICES = 'odoo.get_list_prices';
    case ODOO_GET_STORE_PRODUCTS = 'odoo.get_store_products';
    case NEXT_EVENT = 'next.event';
    case GENERIC_EXTERNAL_CALL = 'generic.external.call';

    public function label(): string
    {
        return str($this->value)->replace('.', ' ')->title()->toString();
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $eventType): string => $eventType->value,
            self::cases(),
        );
    }

    public static function isValid(string $value): bool
    {
        return in_array($value, self::values(), true);
    }
}
