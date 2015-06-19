<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbExpr;
use Swayok\Utils\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbObjectFieldException;
use Swayok\Utils\Utils;

class DateField extends DbObjectField {

    protected $dateFormat = 'Y-m-d';

    protected function doBasicValueValidationAndConvertion($value) {
        if (empty($value)) {
            return null; //< also prevents situation when unixtimestamp = 0 is passed
        }
        if (!ValidateValue::isDateTime($value)) {
            throw new DbObjectFieldException($this, "Value is not date or date-time or has bad formatting");
        }
        return Utils::formatDateTime($value, $this->getDateFormat());
    }

    public function isValidValueFormat($value) {
        if (empty($value) || $value instanceof DbExpr || ValidateValue::isDateTime($value)) {
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