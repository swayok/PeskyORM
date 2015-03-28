<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Lib\ValidateValue;

class IpField extends StringField {

    const IPV4_MIN_LENGTH = 7;
    const IPV4_MAX_LENGTH = 15;

    public function getMinLength() {
        return self::IPV4_MIN_LENGTH;
    }

    public function getMaxLength() {
        return self::IPV4_MAX_LENGTH;
    }

    public function isValidValueFormat($value) {
        $isValid = true;
        if (!empty($value) && !ValidateValue::isIpAddress($value)) {
            $isValid = false;
            $this->setValidationError("Value [{$value}] is not IP address");
        }
        return $isValid;
    }


}