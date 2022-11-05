<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

class DbInsertQueryException extends DbException
{

    public function __construct(string $message)
    {
        parent::__construct($message, static::CODE_INSERT_FAILED);
    }
}