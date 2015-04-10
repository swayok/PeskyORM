<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Lib\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbObjectFieldException;
use PeskyORM\Lib\Utils;

class TimeField extends DbObjectField {

    protected $timeFormat = 'H:i:s';

    protected function doBasicValueValidationAndConvertion($value) {
        if (empty($value)) {
            return null; //< also prevents situation when unixtimestamp = 0 is passed
        }
        if (!ValidateValue::isDateTime($value)) {
            throw new DbObjectFieldException($this, "Value is not time or date-time or has bad formatting");
        }
        return Utils::formatDateTime($value, $this->getTimeFormat());
    }

    public function isValidValueFormat($value) {
        if (empty($value) || ValidateValue::isDateTime($value)) {
            return true;
        }
        $this->setValidationError("Value is not time or date-time or has bad formatting");
        return false;
    }

    /**
     * @return string
     */
    public function getTimeFormat() {
        return $this->timeFormat;
    }

    /**
     * @param string $timeFormat
     */
    public function setTimeFormat($timeFormat) {
        $this->timeFormat = $timeFormat;
    }
}