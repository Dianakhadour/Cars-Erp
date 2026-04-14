<?php
 
namespace App\Enums;

enum EntryType: string
{
    case NORMAL = 'normal';
    case ADJUSTMENT = 'adjustment';
    case REVERSAL = 'reversal';
    case CLOSING = 'closing';

    public function label(): string
    {
        return match($this) {
            self::NORMAL => 'Normal',
            self::ADJUSTMENT => 'Adjustment',
            self::REVERSAL => 'Reversal',
            self::CLOSING => 'Closing',
        };
    }

    public static function forSelect(): array
    {
        return collect(self::cases())->mapWithKeys(function ($type) {
            return [$type->value => $type->label()];
        })->toArray();
    }
}