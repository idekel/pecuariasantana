<?php

namespace App\Enums;

enum ProjectType: string
{
    case Hens = 'hens';
    case MeatChickens = 'meat_chickens';

    /**
     * The unit yields are measured in for this project type.
     */
    public function yieldUnit(): string
    {
        return match ($this) {
            self::Hens => 'eggs',
            self::MeatChickens => 'pounds',
        };
    }

    /**
     * Whether the given quantity is a valid yield amount for this project type.
     * Eggs are counted individually, so hens projects only accept whole numbers.
     */
    public function isValidYieldQuantity(int|float $quantity): bool
    {
        if ($quantity < 0) {
            return false;
        }

        return match ($this) {
            self::Hens => fmod((float) $quantity, 1.0) === 0.0,
            self::MeatChickens => true,
        };
    }
}
