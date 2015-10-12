<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbExpr;
use Swayok\Utils\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbObjectFieldException;

class FloatField extends DbObjectField {

    protected function doBasicValueValidationAndConvertion($value) {
        if ($value === '') {
            return null;
        }
        $this->isValidValueFormat($value, false);
        return $value;
    }

    public function isValidValueFormat($value, $silent = true) {
        if (empty($value) || $value instanceof DbExpr || ValidateValue::isFloat($value, true)) {
            return true;
        }
        $this->setValidationError('Value is not a valid float number', !$silent);
        return false;
    }

}