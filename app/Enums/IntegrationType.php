<?php


namespace App\Enums;

enum IntegrationType: string
{
    // Car ERP Specific Integration Types
    case VEHICLE_SALE = 'vehicle_sale';
    case PARTS_SALE = 'parts_sale';
    case SERVICE_REVENUE = 'service_revenue';
    case VEHICLE_PURCHASE = 'vehicle_purchase';
    case PARTS_PURCHASE = 'parts_purchase';
    case SERVICE_EXPENSE = 'service_expense';
    
    // General Accounting Types
    case PURCHASE = 'purchase';
    case PURCHASE_TAX = 'purchase_tax';
    case SALES = 'sales';
    case SALES_TAX = 'sales_tax';
    case RECEIVABLE = 'receivable';
    case PAYABLE = 'payable';
    case DISCOUNT = 'discount';
    case SHIPPING = 'shipping';
    case CASH = 'cash';
    case BANK = 'bank';
    case EXPENSE = 'expense';
    case INCOME = 'income';

    public function label(): string
    {
        return match($this) {
            self::VEHICLE_SALE => 'Vehicle Sale',
            self::PARTS_SALE => 'Parts Sale',
            self::SERVICE_REVENUE => 'Service Revenue',
            self::VEHICLE_PURCHASE => 'Vehicle Purchase',
            self::PARTS_PURCHASE => 'Parts Purchase',
            self::SERVICE_EXPENSE => 'Service Expense',
            self::PURCHASE => 'Purchase',
            self::PURCHASE_TAX => 'Purchase Tax',
            self::SALES => 'Sales',
            self::SALES_TAX => 'Sales Tax',
            self::RECEIVABLE => 'Accounts Receivable',
            self::PAYABLE => 'Accounts Payable',
            self::DISCOUNT => 'Discount',
            self::SHIPPING => 'Shipping',
            self::CASH => 'Cash',
            self::BANK => 'Bank',
            self::EXPENSE => 'Expense',
            self::INCOME => 'Income',
        };
    }

    public static function forSelect(): array
    {
        return collect(self::cases())->mapWithKeys(function ($type) {
            return [$type->value => $type->label()];
        })->toArray();
    }
}