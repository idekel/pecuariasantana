<?php

namespace App\Enums;

enum ExpenseType: string
{
    case Feed = 'feed';
    case Equipment = 'equipment';
    case OperatingExpense = 'operating_expense';
    case Other = 'other';
}
