<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\DbExpr;
use Swayok\Utils\ValidateValue;
use PeskyORM\DbObjectField;
use Swayok\Utils\Utils;

class TimestampField extends DbObjectField {

    protected $timestampFormat = 'Y-m-d H:i:s';
    protected $dateFormat = 'Y-m-d';
    protected $timeFormat = 'H:i:s';

    protected function doBasicValueValidationAndConvertion($value) {
        if (empty($value)) {
            return null; //< also prevents situation when unixtimestamp = 0 is passed
        }
        $this->isValidValueFormat($value, false);
        return Utils::formatDateTime($value, $this->getTimestampFormat());
    }

    public function isValidValueFormat($value, $silent = true) {
        if (empty($value) || $value instanceof DbExpr || ValidateValue::isDateTime($value)) {
            return true;
        }
        $this->setValidationError('Value is not date-time or has bad formatting', !$silent);
        return false;
    }

    /**
     * @param null|string $format - any format accepted by date(). If null - $this->getDateFormat() is used.
     * @return string
     */
    public function getDate($format = null) {
        return date(empty($format) ? $this->getDateFormat() : $format, $this->getUnixTimestamp());
    }

    /**
     * @param null|string $format - any format accepted by date(). If null - $this->getDateFormat() is used.
     * @return string
     */
    public function getTime($format = null) {
        return date(empty($format) ? $this->getTimeFormat() : $format, $this->getUnixTimestamp());
    }

    /**
     * @return int
     */
    public function getUnixTimestamp() {
        return isset($this->values['value']) ? strtotime($this->values['value']) : 0;
    }

    /**
     * @return string
     */
    public function getTimestampFormat() {
        return $this->timestampFormat;
    }

    /**
     * @param string $timestampFormat
     */
    public function setTimestampFormat($timestampFormat) {
        $this->timestampFormat = $timestampFormat;
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