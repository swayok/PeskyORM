<?php


namespace ORM\DbObjectField;

use PeskyORM\DbObjectField;

class StringField extends DbObjectField {

    protected function doBasicValueValidationAndConvertion($value) {
        return is_string($value) ? $value : '' . $value;
    }

}