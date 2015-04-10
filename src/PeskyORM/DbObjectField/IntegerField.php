<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Lib\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbObjectFieldException;

class IntegerField extends DbObjectField {

    protected function doBasicValueValidationAndConvertion($value) {
        if ($value === '') {
            return null;
        }
        if (!ValidateValue::isInteger($value, true)) {
            throw new DbObjectFieldException($this, "Passed value [{$value}] is not integer number");
        }
        return $value;
    }

}