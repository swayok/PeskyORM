<?php

namespace PeskyORM\Exception;

class OrmException extends \Exception
{
    
    public const CODE_INVALID_TABLE_SCHEMA = 50001;
    public const CODE_INVALID_TABLE_COLUMN_CONFIG = 50002;
    public const CODE_VALUE_NOT_FOUND_EXCEPTION = 40401;
    public const CODE_RECORD_NOT_FOUND_EXCEPTION = 40402;
    public const CODE_INVALID_DATA = 40001;
    
    public const MESSAGE_INVALID_DATA = 'Validation errors: ';
}