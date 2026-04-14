<?php 

namespace App\Enums;

enum ExpensesType: string
{
    case NOT_EXPENSE = 'not_expense';
    case WORK_EXPENSE = 'work_expense';
    case NORMAL_EXPENSE = 'normal_expense';
}