<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbExpr;
use Swayok\Utils\ValidateValue;
use PeskyORM\DbObjectField;
use PeskyORM\Exception\DbObjectFieldException;
use Swayok\Utils\Utils;

class TimeField extends DbObjectField {

    protected $timeFormat = 'H:i:s';

    protected function doBasicValueValidationAndConvertion($value) {
        if (empty($value)) {
            return null; //< also prevents situation when unixtimestamp = 0 is passed
        }
        $this->isValidValueFormat($value, false);
        return Utils::formatDateTime($value, $this->getTimeFormat());
    }

    public function isValidValueFormat($value, $silent = true) {
        if (empty($value) || $value instanceof DbExpr || ValidateValue::isDateTime($value)) {
            return true;
        }
        $this->setValidationError('Value is not time or date-time or has bad formatting', !$silent);
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