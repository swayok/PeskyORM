<?php


namespace PeskyORM\DbObjectField;

use Swayok\Utils\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbObjectFieldException;

class FloatField extends DbObjectField {

    protected function doBasicValueValidationAndConvertion($value) {
        if ($value === '') {
            return null;
        }
        if (!ValidateValue::isFloat($value, true)) {
            throw new DbObjectFieldException($this, "Passed value [{$value}] is not decimal number");
        }
        return $value;
    }
}