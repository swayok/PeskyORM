<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Lib\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbFieldException;

class FloatField extends DbObjectField {

    protected function doBasicValueValidationAndConvertion($value) {
        if ($value === '') {
            return null;
        }
        if (!ValidateValue::isFloat($value, true)) {
            throw new DbFieldException($this, "Passed value [{$value}] is not decimal number");
        }
        return $value;
    }
}