<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

class DbQueryReturningValuesException extends DbQueryException
{

    public function __construct(string $message)
    {
        parent::__construct($message, static::CODE_RETURNING_FAILED);
    }
}