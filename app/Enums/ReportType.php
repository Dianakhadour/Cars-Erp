<?php
 

namespace App\Enums;

enum ReportType: string
{
    case BALANCE_SHEET = 'balance_sheet';
    case INCOME_STATEMENT = 'income_statement';
}