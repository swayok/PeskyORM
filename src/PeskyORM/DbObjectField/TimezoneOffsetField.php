<?php


namespace PeskyORM\DbObjectField;

use Swayok\Utils\Utils;

class TimezoneField extends IntegerField {

    protected $timeFormat = 'H:i:s';

    /**
     * @param mixed $value
     * @return mixed|null|string
     * @throws \PeskyORM\Exception\DbObjectFieldException
     */
    protected function doBasicValueValidationAndConvertion($value) {
        $value = parent::doBasicValueValidationAndConvertion($value);
        if (!empty($value)) {
            return Utils::formatDateTime($value, $this->getTimeFormat(), 0);
        } else {
            return $value;
        }
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