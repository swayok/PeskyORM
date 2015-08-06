<?php

namespace PeskyORM;

use PeskyORM\DbColumnConfig;
use PeskyORM\Exception\DbObjectFieldException;

/**
 * Class DbObjectField
 */
abstract class DbObjectField {
    /**
     * @var DbObject
     */
    protected $dbObject;
    /**
     * @var DbColumnConfig|DbColumnConfig\FileColumnConfig|DbColumnConfig\ImageColumnConfig|DbColumnConfig\PasswordColumnConfig
     */
    protected $dbColumnConfig;
//    public $server = null;     //< server alies where file stored
    /**
     * For $type == DbColumnConfig::TYPE_TIMESTAMP can accept any value that can be passed to strtotime()
     * @var mixed
     */
    protected $defaultValue = DbColumnConfig::DEFAULT_VALUE_NOT_SET;
    /**
     * @var array
     */
    protected $values = array(
        //'value' => mixed,         //< value after $this->convert()
        //'rawValue' => mixed,      //< value in format it was assigned (without type conversion)
        //'dbValue' => mixed,       //< from DB - assigned when isDbValue == true
        //'error' => null|string,   //< validation error
        'isDbValue' => false,       //< indicates that field value must be updated in db
    );
    /**
     * List of related DbObjects aliases. DbObjects stored in model
     * @var DbRelationConfig[]
     */
    protected $relations = array();
    /**
     * @var array
     */
    protected $validators = array();

    /**
     * @param DbObject $dbObject
     * @param DbColumnConfig $dbColumnConfig
     */
    public function __construct(DbObject $dbObject, DbColumnConfig $dbColumnConfig) {
        $this->dbColumnConfig = $dbColumnConfig;
        $this->dbObject = $dbObject;
        foreach ($this->dbColumnConfig->getRelations() as $alias => $relationConfig) {
            $this->addRelation($alias, $relationConfig);
        }
        if ($this->dbColumnConfig->hasDefaultValue()) {
            $this->setDefaultValue($this->dbColumnConfig->getDefaultValue());
        }
        $this->init();
    }

    /**
     * For child classes
     */
    protected function init() {

    }

    /**
     * @return \PeskyORM\DbColumnConfig|DbColumnConfig\FileColumnConfig|DbColumnConfig\ImageColumnConfig
     */
    public function getConfig() {
        return $this->dbColumnConfig;
    }

    /**
     * @return mixed
     * @throws DbObjectFieldException
     */
    public function getDefaultValue() {
        if (!$this->hasDefaultValue()) {
            throw new DbObjectFieldException($this, "Default value is not set");
        }
        return $this->defaultValue;
    }

    /**
     * Returns default value if it is provided or $fallbackValue if not
     * @param mixed $fallbackValue
     * @return mixed
     */
    public function getDefaultValueOr($fallbackValue) {
        return $this->hasDefaultValue() ? $this->defaultValue : $fallbackValue;
    }

    /**
     * @return bool
     */
    public function hasDefaultValue() {
        return $this->defaultValue !== DbColumnConfig::DEFAULT_VALUE_NOT_SET;
    }

    /**
     * @param mixed $defaultValue
     * @return $this
     * @throws DbObjectFieldException
     */
    public function setDefaultValue($defaultValue) {
        $defaultValue = $this->processNewValue($defaultValue);
        if (!$this->isValidValueFormat($defaultValue)) {
            throw new DbObjectFieldException($this, "Invalid default value [{$defaultValue}] provided. Error: {$this->values['error']}");
        }
        $this->defaultValue = $defaultValue;
        return $this;
    }

    public function removeDefaultValue() {
        $this->defaultValue = DbColumnConfig::DEFAULT_VALUE_NOT_SET;
    }

    public function getName() {
        return $this->dbColumnConfig->getName();
    }

    public function getType() {
        return $this->dbColumnConfig->getType();
    }

    public function isPk() {
        return $this->dbColumnConfig->isPk();
    }

    public function isFile() {
        return $this->dbColumnConfig->isFile();
    }

    public function isImage() {
        return $this->dbColumnConfig->isImage();
    }

    public function isUnique() {
        return $this->dbColumnConfig->isUnique();
    }

    public function isVirtual() {
        return $this->dbColumnConfig->isVirtual();
    }

    public function isPrivate() {
        return $this->dbColumnConfig->isPrivate();
    }

    public function canBeNull() {
        return $this->dbColumnConfig->isNullable();
    }

    public function getMinLength() {
        return $this->dbColumnConfig->getMinLength();
    }

    public function getMaxLength() {
        return $this->dbColumnConfig->getMaxLength();
    }

    public function getMinValue() {
        return $this->dbColumnConfig->getMinValue();
    }

    public function getMaxValue() {
        return $this->dbColumnConfig->getMaxValue();
    }

    /**
     * @param $action - self::ON_UPDATE or self::ON_CREATE
     * @return bool
     * @throws \PeskyORM\Exception\DbColumnConfigException
     */
    public function isRequiredOn($action) {
        return $this->dbColumnConfig->isRequiredOn($action);
    }

    /**
     * @return bool
     */
    public function isRequiredOnAnyAction() {
        return $this->dbColumnConfig->isRequiredOnAnyAction();
    }

    /**
     * @param $action - self::ON_UPDATE or self::ON_CREATE
     * @return bool
     * @throws \PeskyORM\Exception\DbColumnConfigException
     */
    public function isExcludedOn($action) {
        return $this->dbColumnConfig->isExcludedOn($action);
    }

    /**
     * @return bool
     */
    public function isExcludedOnAnyAction() {
        return $this->dbColumnConfig->isExcludedOnAnyAction();
    }

    /**
     * @return array
     */
    public function getAllowedValues() {
        return $this->dbColumnConfig->getAllowedValues();
    }

    /**
     * @return DbObject
     */
    public function getDbObject() {
        return $this->dbObject;
    }

    /**
     * Add alias of related object
     * Object itself stored in model
     * @param string $alias
     * @param DbRelationConfig $relationConfig
     */
    protected function addRelation($alias, $relationConfig) {
        if (!isset($alias, $this->relations)) {
            $this->relations[$alias] = $relationConfig;
        }
    }

    /**
     * Get aliases of all relations
     * @return DbRelationConfig[]
     */
    public function getRelations() {
        return $this->relations;
    }

    /**
     * Reset field value to default value or unset
     * @return $this
     */
    public function resetValue() {
        $this->values = array(
            'isDbValue' => false
        );
        return $this;
    }

    /**
     * Restored last db value or resets field when no db value
     * @return $this
     * @throws DbObjectFieldException
     */
    public function restoreDdValueOrReset() {
        if (array_key_exists('dbValue', $this->values)) {
            $this->setValue($this->values['dbValue'], true);
        } else {
            $this->resetValue();
        }
        return $this;
    }

    public function getDbValue() {
        if (array_key_exists('dbValue', $this->values)) {
            $this->values['dbValue'];
        } else {

        }
    }

    /**
     * @param mixed $value
     * @param bool $isDbValue
     * @return $this
     * @throws DbObjectFieldException
     */
    public function setValue($value, $isDbValue = false) {
        if ($this->isVirtual() && $this->dbColumnConfig->importVirtualColumnValueFrom()) {
            throw new DbObjectFieldException(
                $this,
                "Virtual field value [{$this->getName()}] cannot be set directly. " .
                    "Value is imported from field [{$this->dbColumnConfig->importVirtualColumnValueFrom()}]."
            );
        }
        $this->values['rawValue'] = $value;
        $this->values['value'] = $this->processNewValue($this->values['rawValue']);
        $this->setValueReceivedFromDb($isDbValue);
        if ($this->isPk() && $this->values['value'] === null) {
            // null pk value may cause non null violation in db - we don't need it to happen
            $this->resetValue();
        } else {
            $this->validate();
            // value was updated?
            if (!$this->isValueReceivedFromDb() || !$this->isDbValueEqualsTo($this->values['value'])) {
                $this->dbObject->fieldUpdated($this->getName());
            }
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function hasValue() {
        if ($this->isVirtual()) {
            if (!empty($this->dbColumnConfig->importVirtualColumnValueFrom())) {
                return $this->dbObject->_getField($this->dbColumnConfig->importVirtualColumnValueFrom())->hasValue();
            } else {
                return true;
            }
        } else {
            return array_key_exists('value', $this->values);
        }
    }

    /**
     * @return bool
     * @throws DbObjectFieldException
     */
    public function hasNotEmptyValue() {
        return $this->hasValue() && (!empty($this->values['value']) || is_bool($this->values['value']) || is_numeric($this->values['value']));
    }

    /**
     * @param null|mixed $orIfNoValueReturn
     * @return mixed|null|DbFileInfo|DbImageFileInfo
     * @throws DbObjectFieldException
     * @throws Exception\DbObjectException
     */
    public function getValue($orIfNoValueReturn = null) {
        if ($this->isVirtual()) {
            if (!empty($this->dbColumnConfig->importVirtualColumnValueFrom())) {
                return $this->dbObject->_getFieldValue($this->dbColumnConfig->importVirtualColumnValueFrom());
            } else if ($this->dbColumnConfig->hasVirtualColumnValueGenerator()) {
                return call_user_func($this->dbColumnConfig->getVirtualColumnValueGenerator(), $this);
            }
        }
        if (!$this->hasValue() && $this->getName() !== $this->dbObject->_getPkField()) {
            // value not set and not a primary key
            if (!$this->isFile() && $this->dbObject->exists()) {
                // on object update
                // value is set in db but possibly was not fetched
                // to avoid overwriting of correct value object must notify about this situation
                throw new DbObjectFieldException($this, "Field value is not set. Possibly value was not fetched from DB");
            } else {
                // on object create just set default value or null
                $this->setValue($this->getDefaultValueOr(null));
            }
        }
        return $this->hasValue() ? $this->values['value'] : $orIfNoValueReturn;
    }

    /**
     * @return mixed
     */
    public function getRawValue() {
        $this->getValue();
        return $this->values['rawValue'];
    }

    /**
     * @return bool
     */
    public function isValueReceivedFromDb() {
        return $this->values['isDbValue'];
    }

    /**
     * @param bool $fromDb
     * @return $this
     */
    public function setValueReceivedFromDb($fromDb = true) {
        if (!$this->isVirtual() && $this->hasValue()) {
            $this->values['isDbValue'] = !!$fromDb;
            if ($this->values['isDbValue']) {
                $this->values['dbValue'] = $this->values['value'];
            }
        }
        return $this;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    protected function isDbValueEqualsTo($value) {
        return $this->values['dbValue'] === $value;
    }

    /**
     * @return bool
     */
    protected function isConvertEmptyValueToNull() {
        return $this->getConfig()->isConvertEmptyValueToNull();
    }

    /**
     * @return bool
     */
    public function isValid() {
        return empty($this->values['error']);
    }

    /**
     * @return string|null
     */
    public function getValidationError() {
        return $this->isValid() ? null : $this->values['error'];
    }

    /**
     * @param string|array $error
     * @return $this
     */
    public function setValidationError($error) {
        $this->values['error'] = $error;
        return $this;
    }

    /**
     * Imports db object's PK value to use it as field's value
     * @return null|string|int - pk value
     */
    protected function importPkValue() {
        $this->setValue($this->dbObject->_getPkValue());
    }

    /**
     * Analyze new value: validate data type and convert to field type
     * @param mixed $value
     * @return mixed
     * @throws DbObjectFieldException
     */
    protected function processNewValue($value) {
        if (is_object($value) && $value instanceof DbExpr) {
            return $value;
        }
        if ($this->isConvertEmptyValueToNull() && empty($value)) {
            $value = null;
        }
        if ($value === null && !$this->canBeNull()) {
            if ($this->hasDefaultValue()) {
                $value = $this->getDefaultValue();
            } else {
                $value = $this->convertNullValueIfNullIsNotAllowedAndNoDefaultValueProvided();
            }
        }
        if ($value !== null) {
            $value = $this->doBasicValueValidationAndConvertion($value);
            if ($this->isUnique() && empty($value) && $this->canBeNull()) {
                $value = null;
            }
        }
        return $value;
    }

    /**
     * Called when $value is null but null is not allowed and default value is not provided.
     * Should be overwritten in subclasses
     * @return mixed
     */
    protected function convertNullValueIfNullIsNotAllowedAndNoDefaultValueProvided() {
        return null;
    }

    /**
     * Method for basic value validation and convertion.
     * Should be overwritten in subclasses.
     *
     * Validation analyzes value to make sure that value fits field's data type or can be coverted.
     * Convertion updates value so that value fits field's data type.
     * For example: field type is integer, but passed value is numeric string. Value will be validated to contain
     * only digits and then will be converted to integer
     *
     * @param mixed $value
     * @return mixed
     * @throws DbObjectFieldException
     */
    protected function doBasicValueValidationAndConvertion($value) {
        return $value;
    }

    /**
     * Validate field value using $this->canBeNull(), $this->required, $this->getType(), $this->validators
     * @param bool $silent - true: do not throw exception
     * @param bool $forSave - true: allow additional validations (like isUnique)
     * @return bool
     * @throws DbObjectFieldException if $silent == false and value is invalid
     */
    public function validate($silent = true, $forSave = false) {
        unset($this->values['error']);
        // skip validation if value is not set or it is a db value (isDbValue is reliable enough to be used)
        if (!$this->hasValue() || $this->isValueReceivedFromDb() || $this->isVirtual()) {
            return true;
        }
        if (!$this->checkIfRequiredValueIsSet()) {
            $this->setValidationError('Field value is required');
        } else if (!$this->canBeNull() && $this->values['value'] === null) {
            $this->setValidationError('Field value cannot be NULL');
        } else if (!$this->isValidValueLength($this->values['value'])) {
            $this->setValidationError("Value does not match required min-max length");
        } else if (!$this->isValidValueMinMaxRange($this->values['value'])) {
            $this->setValidationError("Value does not match required values range");
        } else if (!$this->isValidValueFormat($this->values['value'])) {
            if (empty($this->values['error'])) {
                $this->setValidationError("Value is invalid");
            }
        } else if ($forSave && !$this->checkIfValueIsUnique()) {
            $this->setValidationError("Value already exists in DB");
        } else {
            $this->runCustomValidators();
        }
        if (!$silent && !$this->isValid()) {
            throw new DbObjectFieldException($this, $this->getValidationError());
        }
        return $this->isValid();
    }

    /**
     * Check if field length does not exceeds $this->length
     * @param mixed $value
     * @return bool
     */
    public function isValidValueLength($value) {
        $isValid = true;
        if ($value !== null) {
            if ($this->getMinLength() > 0) {
                $isValid = mb_strlen($value) >= $this->getMinLength();
            }
            if ($isValid && $this->getMaxLength() > 0) {
                $isValid = mb_strlen($value) <= $this->getMaxLength();
            }
        }
        return $isValid;
    }

    /**
     * Check if field length does not exceeds $this->length
     * @param mixed $value
     * @return bool
     */
    public function isValidValueMinMaxRange($value) {
        $isValid = true;
        if ($this->getMinValue() !== null) {
            $isValid = $value >= $this->getMinValue();
        }
        if ($isValid && $this->getMaxValue() !== null) {
            $isValid = $value <= $this->getMaxValue();
        }
        return $isValid;
    }

    /**
     * Check if required field is not empty (based on $this->required)
     * Returns true if field not required
     * @return bool
     */
    protected function checkIfRequiredValueIsSet() {
        $valid = true;
        if ($this->isRequiredOnAnyAction()) {
            // test if there is any value
            $valid = $this->hasNotEmptyValue();
            // test if value is required for current action
            if (!$valid) {
                $valid = !$this->isRequiredOn($this->dbObject->exists() ? DbColumnConfig::ON_UPDATE : DbColumnConfig::ON_CREATE);
            }
        }
        return $valid;
    }

    /**
     * Test if value is unique
     * @return bool
     */
    protected function checkIfValueIsUnique() {
        $notExists = true;
        if ($this->isUnique() && $this->hasNotEmptyValue()) {
            if (
                !is_numeric($this->values['value'])
                && !in_array($this->getType(), array(DbColumnConfig::TYPE_IPV4_ADDRESS, DbColumnConfig::TYPE_DATE, DbColumnConfig::TYPE_TIME, DbColumnConfig::TYPE_TIMESTAMP))
            ) {
                $conditions = array(
                    'OR' => array(
                        $this->getName() => $this->values['value'],
                        DbExpr::create("lower(`{$this->getName()}`) = lower(``{$this->values['value']}``)")
                    )
                );
            } else {
                $conditions = array(
                    $this->getName() => $this->values['value'],
                );
            }
            if ($this->dbObject->exists()) {
                $conditions[$this->dbObject->_getPkFieldName() . '!='] = $this->dbObject->_getPkValue();
            }
            $notExists = !$this->dbObject->_getModel()->exists($conditions);
        }
        return $notExists;
    }

    /**
     * Check if value has valid format
     * @param $value
     * @return bool
     * @throws DbObjectFieldException
     */
    public function isValidValueFormat($value) {
        return true;
    }

    /**
     * Apply custom validators
     * @return bool
     * @throws DbObjectFieldException
     */
    protected function runCustomValidators() {
        if ($this->dbColumnConfig->hasCustomValidators()) {
            foreach ($this->dbColumnConfig->getCustomValidators() as $validator) {
                if (!$validator($this, $this->values['value'])) {
                    if (empty($this->getValidationError())) {
                        throw new DbObjectFieldException($this, 'Custom validators should call DbObjectField->setValidationError() for invalid value');
                    }
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * If this field can be skipped when value (is not set, has no default value on creations and not requred) or is virtual or excluded
     * @return bool
     */
    public function canBeSkipped() {
        $action = $this->dbObject->exists() ? DbColumnConfig::ON_UPDATE : DbColumnConfig::ON_CREATE;
        if (
            $this->isVirtual()
            || $this->isExcludedOn($action)
        ) {
            return true;
        } else if ($action === DbColumnConfig::ON_UPDATE) {
            // skip on update when not set and not required
            return !$this->hasValue() && $this->checkIfRequiredValueIsSet();
        } else {
            // skip on create when not set, has no default value and not required
            return !$this->hasValue() && !$this->hasDefaultValue() && $this->checkIfRequiredValueIsSet();
        }
    }

}