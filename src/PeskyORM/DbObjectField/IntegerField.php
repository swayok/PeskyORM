<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbExpr;
use Swayok\Utils\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbObjectFieldException;

class IntegerField extends DbObjectField {

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
        if (empty($value) || $value instanceof DbExpr || ValidateValue::isInteger($value, true)) {
            return true;
        }
        $this->setValidationError("Value is not a valid integer number");
        return false;
    }

}