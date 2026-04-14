<?php

namespace App\Enums;

enum JournalEntryType: string
{
    case MANUAL = 'manual';
    case INVOICE = 'invoice';
    case PURCHASE = 'purchase';
    case PAYROLL = 'payroll';
    case CLOSING = 'closing';
    case ADJUSTMENT = 'adjustment';
    
    // Car ERP Specific Types
    case VEHICLE_SALE = 'vehicle_sale';
    case PARTS_SALE = 'parts_sale';
    case SERVICE_INVOICE = 'service_invoice';
    case VEHICLE_PURCHASE = 'vehicle_purchase';
    case PARTS_PURCHASE = 'parts_purchase';

    public function label(): string
    {
        return match($this) {
            self::MANUAL => 'Manual Entry',
            self::INVOICE => 'Invoice',
            self::PURCHASE => 'Purchase',
            self::PAYROLL => 'Payroll',
            self::CLOSING => 'Closing Entry',
            self::ADJUSTMENT => 'Adjustment',
            self::VEHICLE_SALE => 'Vehicle Sale',
            self::PARTS_SALE => 'Parts Sale',
            self::SERVICE_INVOICE => 'Service Invoice',
            self::VEHICLE_PURCHASE => 'Vehicle Purchase',
            self::PARTS_PURCHASE => 'Parts Purchase',
        };
    }
}