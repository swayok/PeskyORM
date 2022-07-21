<?php

namespace PeskyORM\Exception;

class ValueNotFoundException extends OrmException
{
    
    public function __construct($message, $previous = null)
    {
        parent::__construct($message, self::CODE_VALUE_NOT_FOUND_EXCEPTION, $previous);
    }
    
}