<?php

namespace App\Exceptions;

use InvalidArgumentException;

class CannotGrantAccessToOwnerException extends InvalidArgumentException
{
    public function __construct()
    {
        parent::__construct('The project owner already has access to the project.');
    }
}