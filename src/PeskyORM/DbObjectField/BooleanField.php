<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbObjectField;

class BooleanField extends DbObjectField {

    protected function convertNullValueIfNullIsNotAllowedAndNoDefaultValueProvided() {
        return false;
    }

    protected function doBasicValueValidationAndConvertion($value) {
        if (is_string($value) && strtolower($value) === 'false') {
            $value = false;
        } else if ($value === '' || $value === null) {
            $value = null;
        } else {
            $value = !!$value;
        }
        return $value;
    }


}