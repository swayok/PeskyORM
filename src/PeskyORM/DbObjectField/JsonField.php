<?php


namespace PeskyORM\DbObjectField;

use Swayok\Utils\ValidateValue;

class JsonField extends TextField {

    protected function doBasicValueValidationAndConvertion($value) {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return parent::doBasicValueValidationAndConvertion($value);
    }

    public function isValidValueFormat($value, $silent = true) {
        if (empty($value) || ValidateValue::isJson($value)) {
            return true;
        }
        $this->setValidationError('Value is not JSON', !$silent);
        return false;
    }

    public function getArray() {
        return isset($this->values['value']) ? json_decode($this->values['value'], true) : [];
    }


}