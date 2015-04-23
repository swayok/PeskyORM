<?php


namespace PeskyORM\DbObjectField;

use Swayok\Utils\ValidateValue;

class EmailField extends StringField {

    public function isValidValueFormat($value) {
        if (empty($value) || ValidateValue::isEmail($value)) {
            return true;
        }
        $this->setValidationError("Value is not a valid email address");
        return false;
    }


}