<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Lib\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbFieldException;
use PeskyORM\Lib\Utils;

class DateField extends DbObjectField {

    protected $dateFormat = 'Y-m-d';

    protected function doBasicValueValidationAndConvertion($value) {
        if (empty($value)) {
            return null; //< also prevents situation when unixtimestamp = 0 is passed
        }
        if (!ValidateValue::isDateTime($value)) {
            throw new DbFieldException($this, "Value [{$value}] is not date or date-time or has bad formatting");
        }
        return Utils::formatDateTime($value, $this->getDateFormat());
    }

    protected function isValidValueFormat($value) {
        $isValid = true;
        if (!empty($value) && !ValidateValue::isDateTime($value)) {
            $isValid = false;
            $this->setValidationError("Value [{$value}] is not date or date-time or has bad formatting");
        }
        return $isValid;
    }

    /**
     * @return string
     */
    public function getDateFormat() {
        return $this->dateFormat;
    }

    /**
     * @param string $dateFormat
     */
    public function setDateFormat($dateFormat) {
        $this->dateFormat = $dateFormat;
    }

}