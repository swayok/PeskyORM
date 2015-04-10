<?php


namespace PeskyORM\DbObjectField;

use PeskyORM\Exception\DbObjectFieldException;

/**
 * Class PasswordField
 * @package PeskyORM\DbObjectField
 */
class PasswordField extends Sha1Field {

    protected function doBasicValueValidationAndConvertion($value) {
        if (!empty($value)) {
            $value = $this->hashPassword($value);
        }
        return parent::doBasicValueValidationAndConvertion($value);
    }

    public function canBeSkipped() {
        return $this->isValueReceivedFromDb() || parent::canBeSkipped();
    }

    public function setValueReceivedFromDb($fromDb = true) {
        if ($this->hasValue() && $fromDb) {
            $this->values['isDbValue'] = true;
            // reset hashing done by doBasicValueValidationAndConvertion
            $this->values['dbValue'] = $this->values['value'] = $this->values['rawValue'];
        } else {
            parent::setValueReceivedFromDb(false);
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