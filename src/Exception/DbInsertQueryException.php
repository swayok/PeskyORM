<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

class DbInsertQueryException extends DbQueryException
{
    public function __construct(string $message, string $query)
    {
        parent::__construct($message, $query, static::CODE_INSERT_FAILED);
    }
}