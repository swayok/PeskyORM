<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

class RecordNotFoundException extends OrmException
{

    public function __construct(string $message, \Throwable $previous = null)
    {
        parent::__construct($message, self::CODE_RECORD_NOT_FOUND_EXCEPTION, $previous);
    }

}