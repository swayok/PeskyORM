<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Exception\DbFieldException;

class Sha1Field extends StringField {

    const SHA1_LENGTH = 40;

    protected function doBasicValueValidationAndConvertion($value) {
        $value = parent::doBasicValueValidationAndConvertion($value);
        if (!empty($value) && !$this->isValidValueLength($value)) {
            throw new DbFieldException($this, "Passed value [{$value}] does not match SHA1 hash sring length (" . self::SHA1_LENGTH . ')');
        }
        return $value;
    }

    public function getMinLength() {
        return self::SHA1_LENGTH;
    }

    public function getMaxLength() {
        return self::SHA1_LENGTH;
    }
}