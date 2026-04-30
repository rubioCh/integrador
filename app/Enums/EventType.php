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
    case HUBSPOT_CONTACT_PROPERTY_CHANGE = 'contact.propertyChange';
    case HUBSPOT_COMPANY_PROPERTY_CHANGE = 'company.propertyChange';
    case HUBSPOT_DEAL_PROPERTY_CHANGE = 'deal.propertyChange';
    case HUBSPOT_OBJECT_PROPERTY_CHANGE = 'object.propertyChange';
    case HUBSPOT_PROPERTY_CHANGED_LEGACY = 'hubspot.property.changed';
    case ODOO_GET_LIST_PRICES = 'odoo.get_list_prices';
    case ODOO_GET_STORE_PRODUCTS = 'odoo.get_store_products';
    case AZURE_SQL_PRODUCTS_SYNC = 'azure_sql.products.sync';
    case AZURE_SQL_ACCOUNTS_SYNC = 'azure_sql.accounts.sync';
    case AZURE_SQL_CONTACTS_SYNC = 'azure_sql.contacts.sync';
    case NEXT_EVENT = 'next.event';
    case GENERIC_EXTERNAL_CALL = 'generic.external.call';

    public function label(): string
    {
        return match ($this) {
            self::COMPANY_CREATED => 'Company Created',
            self::COMPANY_UPDATED => 'Company Updated',
            self::PRODUCT_CREATED => 'Product Created',
            self::PRODUCT_UPDATED => 'Product Updated',
            self::INVOICE_CREATED => 'Invoice Created',
            self::INVOICE_RECURRING_CREATED => 'Recurring Invoice Created',
            self::SALE_ORDER_CREATED => 'Sale Order Created',
            self::QUOTES_SENDING_DATA => 'Quotes Sending Data',
            self::RESPONSE_SEND => 'Response Send',
            self::OBJECT_UPDATED => 'Object Updated',
            self::HUBSPOT_CONTACT_PROPERTY_CHANGE => 'HubSpot Contact Property Change',
            self::HUBSPOT_COMPANY_PROPERTY_CHANGE => 'HubSpot Company Property Change',
            self::HUBSPOT_DEAL_PROPERTY_CHANGE => 'HubSpot Deal Property Change',
            self::HUBSPOT_OBJECT_PROPERTY_CHANGE => 'HubSpot Object Property Change',
            self::HUBSPOT_PROPERTY_CHANGED_LEGACY => 'HubSpot Property Changed (Legacy)',
            self::ODOO_GET_LIST_PRICES => 'Odoo Get List Prices',
            self::ODOO_GET_STORE_PRODUCTS => 'Odoo Get Store Products',
            self::AZURE_SQL_PRODUCTS_SYNC => 'Azure SQL Products Sync',
            self::AZURE_SQL_ACCOUNTS_SYNC => 'Azure SQL Accounts Sync',
            self::AZURE_SQL_CONTACTS_SYNC => 'Azure SQL Contacts Sync',
            self::NEXT_EVENT => 'Next Event',
            self::GENERIC_EXTERNAL_CALL => 'Generic External Call',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::COMPANY_CREATED => 'Creates or forwards a company entity in the integration flow.',
            self::COMPANY_UPDATED => 'Updates a company entity in the destination platform.',
            self::PRODUCT_CREATED => 'Creates a product in the destination platform.',
            self::PRODUCT_UPDATED => 'Updates an existing product in the destination platform.',
            self::INVOICE_CREATED => 'Creates an invoice or downstream billing artifact.',
            self::INVOICE_RECURRING_CREATED => 'Creates a recurring invoice or subscription billing artifact.',
            self::SALE_ORDER_CREATED => 'Creates a sale order in the destination platform.',
            self::QUOTES_SENDING_DATA => 'Transfers quote payload data for downstream processing.',
            self::RESPONSE_SEND => 'Sends a response payload to the next integration step.',
            self::OBJECT_UPDATED => 'Updates a generic object using mapped properties.',
            self::HUBSPOT_CONTACT_PROPERTY_CHANGE => 'Handles HubSpot contact.propertyChange subscription payloads.',
            self::HUBSPOT_COMPANY_PROPERTY_CHANGE => 'Handles HubSpot company.propertyChange subscription payloads.',
            self::HUBSPOT_DEAL_PROPERTY_CHANGE => 'Handles HubSpot deal.propertyChange subscription payloads.',
            self::HUBSPOT_OBJECT_PROPERTY_CHANGE => 'Handles HubSpot object.propertyChange subscription payloads.',
            self::HUBSPOT_PROPERTY_CHANGED_LEGACY => 'Legacy alias kept for compatibility with previous configurations.',
            self::ODOO_GET_LIST_PRICES => 'Fetches Odoo price list data for products or variants.',
            self::ODOO_GET_STORE_PRODUCTS => 'Fetches store products from Odoo catalogs.',
            self::AZURE_SQL_PRODUCTS_SYNC => 'Reads product rows from Azure SQL and updates existing HubSpot products.',
            self::AZURE_SQL_ACCOUNTS_SYNC => 'Reads customer/account rows from Azure SQL and updates existing HubSpot companies.',
            self::AZURE_SQL_CONTACTS_SYNC => 'Reads contact locator rows from Azure SQL and reconciles existing HubSpot contacts.',
            self::NEXT_EVENT => 'Internal flow-control event used to continue a pipeline.',
            self::GENERIC_EXTERNAL_CALL => 'Executes an HTTP call for a generic platform without an SDK.',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::HUBSPOT_CONTACT_PROPERTY_CHANGE,
            self::HUBSPOT_COMPANY_PROPERTY_CHANGE,
            self::HUBSPOT_DEAL_PROPERTY_CHANGE,
            self::HUBSPOT_OBJECT_PROPERTY_CHANGE => 'HubSpot',
            self::HUBSPOT_PROPERTY_CHANGED_LEGACY => 'Legacy',
            self::ODOO_GET_LIST_PRICES,
            self::ODOO_GET_STORE_PRODUCTS => 'Odoo Sync',
            self::AZURE_SQL_PRODUCTS_SYNC,
            self::AZURE_SQL_ACCOUNTS_SYNC,
            self::AZURE_SQL_CONTACTS_SYNC => 'Azure SQL Sync',
            self::GENERIC_EXTERNAL_CALL => 'Generic HTTP',
            self::NEXT_EVENT => 'Flow Control',
            default => 'Core Events',
        };
    }

    /**
     * @return list<string>
     */
    public function suggestedPlatforms(): array
    {
        return match ($this) {
            self::HUBSPOT_CONTACT_PROPERTY_CHANGE,
            self::HUBSPOT_COMPANY_PROPERTY_CHANGE,
            self::HUBSPOT_DEAL_PROPERTY_CHANGE,
            self::HUBSPOT_OBJECT_PROPERTY_CHANGE,
            self::HUBSPOT_PROPERTY_CHANGED_LEGACY => ['hubspot'],
            self::ODOO_GET_LIST_PRICES,
            self::ODOO_GET_STORE_PRODUCTS => ['odoo'],
            self::AZURE_SQL_PRODUCTS_SYNC,
            self::AZURE_SQL_ACCOUNTS_SYNC,
            self::AZURE_SQL_CONTACTS_SYNC => ['generic'],
            self::GENERIC_EXTERNAL_CALL => ['generic'],
            default => ['*'],
        };
    }

    public function isSuggestedForPlatform(?string $platformType): bool
    {
        if (! $platformType) {
            return true;
        }

        $platforms = $this->suggestedPlatforms();

        return in_array('*', $platforms, true) || in_array($platformType, $platforms, true);
    }

    public function eventClass(): ?string
    {
        return match ($this) {
            self::COMPANY_CREATED => \App\Events\Company\CreateCompanyEvent::class,
            self::COMPANY_UPDATED => \App\Events\Company\UpdateCompanyEvent::class,
            self::PRODUCT_CREATED => \App\Events\Product\CreateProductEvent::class,
            self::PRODUCT_UPDATED => \App\Events\Product\UpdateProductEvent::class,
            self::INVOICE_CREATED => \App\Events\Invoice\CreateInvoiceEvent::class,
            self::INVOICE_RECURRING_CREATED => \App\Events\Invoice\CreateRecurringInvoiceEvent::class,
            self::SALE_ORDER_CREATED => \App\Events\SaleOrder\CreateSaleOrderEvent::class,
            self::QUOTES_SENDING_DATA => \App\Events\Quotes\SendingQuotesDataEvent::class,
            self::RESPONSE_SEND => \App\Events\Response\SendResponseEvent::class,
            self::OBJECT_UPDATED => \App\Events\Object\UpdateObjectEvent::class,
            self::HUBSPOT_CONTACT_PROPERTY_CHANGE => \App\Events\Object\UpdateObjectEvent::class,
            self::HUBSPOT_COMPANY_PROPERTY_CHANGE => \App\Events\Object\UpdateObjectEvent::class,
            self::HUBSPOT_DEAL_PROPERTY_CHANGE => \App\Events\Object\UpdateObjectEvent::class,
            self::HUBSPOT_OBJECT_PROPERTY_CHANGE => \App\Events\Object\UpdateObjectEvent::class,
            self::HUBSPOT_PROPERTY_CHANGED_LEGACY => \App\Events\Object\UpdateObjectEvent::class,
            self::ODOO_GET_LIST_PRICES => \App\Events\Odoo\GetListPricesEvent::class,
            self::ODOO_GET_STORE_PRODUCTS => \App\Events\Odoo\GetStoreProductsEvent::class,
            self::AZURE_SQL_PRODUCTS_SYNC => \App\Events\Object\UpdateObjectEvent::class,
            self::AZURE_SQL_ACCOUNTS_SYNC => \App\Events\Object\UpdateObjectEvent::class,
            self::AZURE_SQL_CONTACTS_SYNC => \App\Events\Object\UpdateObjectEvent::class,
            self::NEXT_EVENT => \App\Events\NextEvent::class,
            self::GENERIC_EXTERNAL_CALL => \App\Events\Generic\ExternalCallEvent::class,
        };
    }

    public function toOption(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'group' => $this->group(),
            'platforms' => $this->suggestedPlatforms(),
        ];
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

    public static function tryFromSubscriptionType(?string $value): ?self
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = trim(strtolower($value));

        foreach (self::cases() as $eventType) {
            if (strtolower($eventType->value) === $normalized) {
                return $eventType;
            }
        }

        return null;
    }

    public static function groupedOptions(?string $platformType = null): array
    {
        $groups = [];

        foreach (self::cases() as $eventType) {
            if ($eventType === self::HUBSPOT_PROPERTY_CHANGED_LEGACY) {
                continue;
            }

            if (! $eventType->isSuggestedForPlatform($platformType)) {
                continue;
            }

            $group = $eventType->group();

            if (! isset($groups[$group])) {
                $groups[$group] = [
                    'label' => $group,
                    'options' => [],
                ];
            }

            $groups[$group]['options'][] = $eventType->toOption();
        }

        return array_values($groups);
    }
}
