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
        if (!$this->isValidValueFormat($value)) {
            throw new DbObjectFieldException($this, $this->getValidationError());
        }
        return $value;
    }

    public function isValidValueFormat($value) {
        if (empty($value) || $value instanceof DbExpr || ValidateValue::isFloat($value, true)) {
            return true;
        }
        $this->setValidationError("Value is not a valid float number");
        return false;
    }

}