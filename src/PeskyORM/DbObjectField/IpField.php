<?php


namespace PeskyORM\DbObjectField;

use Swayok\Utils\ValidateValue;

class IpField extends StringField {

    const IPV4_MIN_LENGTH = 7;
    const IPV4_MAX_LENGTH = 15;

//    public function getMinLength() {
//        return self::IPV4_MIN_LENGTH;
//    }
//
//    public function getMaxLength() {
//        return self::IPV4_MAX_LENGTH;
//    }

    public function isValidValueFormat($value, $silent = true) {
        if (empty($value) || ValidateValue::isIpAddress($value)) {
            return true;
        }
        $this->setValidationError('Value is not IP address', !$silent);
        return false;
    }


}