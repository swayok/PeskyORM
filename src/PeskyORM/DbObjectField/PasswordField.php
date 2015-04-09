<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Exception\DbFieldException;

/**
 * Class PasswordField
 * @package PeskyORM\DbObjectField
 *
 * Idea behind this field:
 * 1. Any value received from db will be ignored and eraseds so getValue() will return empty string
 * 2. Any value not received from db will be hased with hashPassword() method
 */
class PasswordField extends Sha1Field {

    protected function doBasicValueValidationAndConvertion($value) {
        if (!empty($value)) {
            $value = $this->hashPassword($value);
        }
        return parent::doBasicValueValidationAndConvertion($value);
    }

    public function getValue($orIfNoValueReturn = null) {
        return $this->isValueReceivedFromDb() ? '' : parent::getValue($orIfNoValueReturn);
    }

    public function canBeSkipped() {
        return $this->isValueReceivedFromDb() || parent::canBeSkipped();
    }

    public function setValue($value, $isDbValue = false) {
        if ($isDbValue) {
            $this->setValueReceivedFromDb(true);
        } else {
            parent::setValue($value, false);
        }
        return $this;
    }

    public function setValueReceivedFromDb($fromDb = true) {
        if ($this->hasValue() && $fromDb) {
            $this->resetValue();
            $this->values['value'] = '';
            $this->values['isDbValue'] = true;
        } else {
            $this->values['isDbValue'] = false;
        }
        return $this;
    }

    /**
     * @param $password
     * @return string
     */
    public function hashPassword($password) {
        if ($this->dbColumnConfig->hasHashFunction()) {
            return call_user_func($this->dbColumnConfig->getHashFunction(), $password);
        } else {
            return sha1($password);
        }
    }

    /**
     * Compare field's value with passed password
     * @param string $plainPassword
     * @return bool
     */
    public function compareWith($plainPassword) {
        if ($this->hasValue()) {
            return $this->getValue() === $this->hashPassword($plainPassword);
        } else {
            return false;
        }
    }

}