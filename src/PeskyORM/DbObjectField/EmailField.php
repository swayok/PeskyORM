<?php


namespace PeskyORM\DbObjectField;

use Swayok\Utils\ValidateValue;

class EmailField extends StringField {

    public function isValidValueFormat($value, $silent = true) {
        if (empty($value) || ValidateValue::isEmail($value)) {
            return true;
        }
        $this->setValidationError('Value is not a valid email address', !$silent);
        return false;
    }


}