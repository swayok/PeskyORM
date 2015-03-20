<?php


namespace ORM\DbObjectField;

class EnumField extends StringField {

    protected function isValidValueFormat($value) {
        $isValid = true;
        if (!empty($value) && (empty($this->getAllowedValues()) || !in_array($value, $this->getAllowedValues()))) {
            $isValid = false;
            $this->setValidationError("Value [{$value}] is not allowed");
        }
        return $isValid;
    }


}