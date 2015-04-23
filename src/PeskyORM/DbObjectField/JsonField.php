<?php


namespace PeskyORM\DbObjectField;

use Swayok\Utils\Utils;

class JsonField extends TextField {

    protected function doBasicValueValidationAndConvertion($value) {
        if (is_array($value)) {
            $value = Utils::jsonEncodeCyrillic($value);
        }
        return parent::doBasicValueValidationAndConvertion($value);
    }

    public function isValidValueFormat($value) {
        if (empty($value) || json_decode($value, true) !== false) {
            return true;
        }
        $this->setValidationError("Value is not JSON");
        return false;
    }


}