<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Lib\Utils;

class JsonField extends TextField {

    protected function doBasicValueValidationAndConvertion($value) {
        if (is_array($value)) {
            $value = Utils::jsonEncodeCyrillic($value);
        }
        return parent::doBasicValueValidationAndConvertion($value);
    }

    public function isValidValueFormat($value) {
        $isValid = true;
        if (!empty($value) && json_decode($value, true) === false) {
            $isValid = false;
            $this->setValidationError("Value [$value] is not JSON");
        }
        return $isValid;
    }


}