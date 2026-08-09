<?php

namespace App\Exceptions;

use App\Enums\ProjectType;
use InvalidArgumentException;

class InvalidSaleQuantityException extends InvalidArgumentException
{
    public static function forProjectType(ProjectType $type, int|float $quantity): self
    {
        return new self(sprintf(
            'The quantity [%s] is not a valid %s sale.',
            $quantity,
            $type->yieldUnit()
        ));
    }
}
