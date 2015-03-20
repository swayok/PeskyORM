<?php


namespace ORM\DbObjectField;

use ORM\Lib\ValidateValue;

class EmailField extends StringField {

    protected function isValidValueFormat($value) {
        $isValid = true;
        if (!empty($value) && ValidateValue::isEmail($value)) {
            $isValid = false;
            $this->setValidationError("Value [{$value}] is not valid email address");
        }
        return $isValid;
    }


}