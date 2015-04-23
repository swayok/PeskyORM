<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Exception\DbObjectFieldException;
use Swayok\Utils\ValidateValue;

class Md5Field extends StringField {

    const MD5_LENGTH = 32;

    protected function doBasicValueValidationAndConvertion($value) {
        $value = parent::doBasicValueValidationAndConvertion($value);
        if (!empty($value) && !$this->isValidValueLength($value)) {
            throw new DbObjectFieldException($this, "Passed value [{$value}] does not match MD5 hash sring length (" . self::MD5_LENGTH . ')');
        }
        return $value;
    }

    public function getMinLength() {
        return self::MD5_LENGTH;
    }

    public function getMaxLength() {
        return self::MD5_LENGTH;
    }

    public function isValidValueFormat($value) {
        if (empty($value) || ValidateValue::isMd5($value)) {
            return true;
        }
        $this->setValidationError("Value is not valid");
        return false;
    }

}