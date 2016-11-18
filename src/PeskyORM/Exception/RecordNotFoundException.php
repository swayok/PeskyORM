<?php

namespace PeskyORM\Exception;

class RecordNotFoundException extends OrmException {

    public function __construct($message, $previous = null) {
        parent::__construct($message, self::CODE_RECORD_NOT_FOUND_EXCEPTION, $previous);
    }

}