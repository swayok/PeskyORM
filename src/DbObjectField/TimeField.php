<?php


namespace ORM\DbObjectField;

use ORM\Lib\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbFieldException;
use PeskyORM\Lib\Utils;

class TimeField extends DbObjectField {

    protected $timeFormat = 'H:i:s';

    protected function doBasicValueValidationAndConvertion($value) {
        if (empty($value)) {
            return null; //< also prevents situation when unixtimestamp = 0 is passed
        }
        if (!ValidateValue::isDateTime($value)) {
            throw new DbFieldException($this, "Value [{$value}] is not time or date-time or has bad formatting");
        }
        return Utils::formatDateTime($value, $this->getTimeFormat());
    }

    protected function isValidValueFormat($value) {
        $isValid = true;
        if (!empty($value) && !ValidateValue::isDateTime($value)) {
            $isValid = false;
            $this->setValidationError("Value [{$value}] is not time or date-time or has bad formatting");
        }
        return $isValid;
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