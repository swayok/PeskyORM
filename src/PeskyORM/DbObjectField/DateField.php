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
            throw new DbFieldException($this, "Value is not date or date-time or has bad formatting");
        }
        return Utils::formatDateTime($value, $this->getDateFormat());
    }

    public function isValidValueFormat($value) {
        if (empty($value) || ValidateValue::isDateTime($value)) {
            return true;
        }
        $this->setValidationError("Value is not date or date-time or has bad formatting");
        return false;
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