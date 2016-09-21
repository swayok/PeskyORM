<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbExpr;
use Swayok\Utils\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbObjectFieldException;

class IntegerField extends DbObjectField {

    protected function doBasicValueValidationAndConvertion($value) {
        if ($value === '' || $value === null) {
            return null;
        }
        $this->isValidValueFormat($value, false);
        return (int)$value;
    }

    public function isValidValueFormat($value, $silent = true) {
        if (empty($value) || $value instanceof DbExpr || ValidateValue::isInteger($value, true)) {
            return true;
        }
        $this->setValidationError('Value is not a valid integer number', !$silent);
        return false;
    }

}