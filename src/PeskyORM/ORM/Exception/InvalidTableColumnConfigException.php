<?php

namespace PeskyORM\ORM\Exception;

use Exception;

class InvalidTableColumnConfigException extends OrmException {
    
    public function __construct($message, Exception $previous = null) {
        parent::__construct($message, static::CODE_INVALID_TABLE_COLUMN_CONFIG, $previous);
    }


}