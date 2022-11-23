<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

class DbAdapterDoesNotSupportFeature extends DbException
{
    public function __construct(string $message)
    {
        parent::__construct($message, static::CODE_DB_DOES_NOT_SUPPORT_FEATURE);
    }

}