<?php


namespace ORM\DbObjectField;

use ORM\Lib\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbFieldException;

class IntegerField extends DbObjectField {

    protected function doBasicValueValidationAndConvertion($value) {
        if ($value === '') {
            return null;
        }
        if (!ValidateValue::isInteger($value, true)) {
            throw new DbFieldException($this, "Passed value [{$value}] is not integer number");
        }
        return $value;
    }

}