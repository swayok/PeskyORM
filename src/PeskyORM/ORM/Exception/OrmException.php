<?php

namespace PeskyORM\ORM\Exception;

class OrmException extends \Exception {

    const CODE_INVALID_TABLE_SCHEMA = 50001;
    const CODE_INVALID_TABLE_COLUMN_CONFIG = 50002;
    const CODE_VALUE_NOT_FOUND_EXCEPTION = 40401;
    const CODE_INVALID_DATA = 40001;

    const MESSAGE_INVALID_DATA = 'error.invalid_data';
}