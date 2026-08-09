<?php

namespace App\Exceptions;

use RuntimeException;

class UnauthorizedProjectActionException extends RuntimeException
{
    public static function grantAccess(): self
    {
        return new self('Only the project owner can grant access to this project.');
    }

    public static function revokeAccess(): self
    {
        return new self('Only the project owner can revoke access to this project.');
    }

    public static function recordYield(): self
    {
        return new self('The user does not have access to record yields for this project.');
    }

    public static function recordSale(): self
    {
        return new self('The user does not have access to record sales for this project.');
    }

    public static function manageYields(): self
    {
        return new self('The user does not have access to manage yields for this project.');
    }
}
