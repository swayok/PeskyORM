<?php


namespace PeskyORM\DbObjectField;

class EnumField extends StringField {

    public function isValidValueFormat($value, $silent = true) {
        if (empty($value) || empty($this->getAllowedValues()) || in_array($value, $this->getAllowedValues())) {
            return true;
        }
        $this->setValidationError('Value is not allowed', !$silent);
        return false;
    }


}