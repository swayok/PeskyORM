<?php


namespace ORM\DbObjectField;

use PeskyORM\DbObjectField;

class TimestampField extends DbObjectField {

    protected $timestampFormat = 'Y-m-d H:i:s';
    protected $dateFormat = 'Y-m-d';
    protected $timeFormat = 'H:i:s';

    /**
     * Converts $value to required date-time format
     * @param int|string $value - int: unix timestamp | string: valid date/time/date-time string
     * @param string $format - resulting value format
     * @param string|int|bool $now - current unix timestamp or any valid strtotime() string
     * @return string
     */
    protected function formatDateTime($value, $format, $now = 'now') {
        if (empty($value)) {
            $value = null;
        } else if (is_int($value) || is_numeric($value)) {
            $value = date($format, $value);
        } else if (strtotime($value) != 0) {
            // convert string value to unix timestamp and then to required date format
            $value = date($format, strtotime($value, is_string($now) && !is_numeric($now) ? strtotime($now) : 0));
        }
        return $value;
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