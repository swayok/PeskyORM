<?php

namespace PeskyORM;

use ORM\DbColumnConfig;
use PeskyORM\Exception\DbFieldException;

/**
 * Class DbObjectField
 */
abstract class DbObjectField {
    /**
     * @var DbObject
     */
    protected $dbObject;
    /**
     * @var DbColumnConfig
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
        //'rawDbValue' => mixed,    //< raw value from DB - assigned on $this->isDbValue = true
        //'error' => null|string,   //< validation error
        //'file_path' => null|string|array,   //< fs path to files
        'isset' => false,           //< used to find out if value was ever set (to any value)
        'isDbValue' => false,       //< indicates that field value must be updated in db
    );
    /**
     * List of related DbObjects aliases. DbObjects stored in model
     * @var array
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
//        if (!empty($info['server'])) {
//            $this->server = $info['server'];
//        }
//        if (!empty($info['validators']) && is_array($info['validators'])) {
//            $this->validators = $info['validators'];
//        }
        if ($this->dbColumnConfig->hasDefaultValue()) {
            $this->setDefaultValue($this->dbColumnConfig->getDefaultValue());
        }
    }

    /**
     * @return mixed
     * @throws DbFieldException
     */
    public function getDefaultValue() {
        if (!$this->hasDefaultValue()) {
            throw new DbFieldException($this, "Default value is not set");
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
     * @throws DbFieldException
     */
    public function setDefaultValue($defaultValue) {
        $defaultValue = $this->processNewValue($defaultValue);
        if (!$this->isValidValueFormat($defaultValue)) {
            throw new DbFieldException($this, "Invalid default value [{$defaultValue}] provided. Error: {$this->values['error']}");
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

    public function canBeNull() {
        return $this->dbColumnConfig->isNullable();
    }

    public function getMinLength() {
        return $this->dbColumnConfig->getMinLength();
    }

    public function getMaxLength() {
        return $this->dbColumnConfig->getMaxLength();
    }

    /**
     * @param $action - self::ON_UPDATE or self::ON_CREATE
     * @return bool
     * @throws \ORM\Exception\DbColumnConfigException
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
     * @throws \ORM\Exception\DbColumnConfigException
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
     */
    public function addRelation($alias) {
        if (!in_array($alias, $this->relations)) {
            $this->relations[] = $alias;
        }
    }

    /**
     * Get aliases of all relations
     * @return array
     */
    public function getRelations() {
        return $this->relations;
    }

    /**
     * Reset field value to default value or unset
     */
    public function resetValue() {
        $this->values = array(
            'isset' => false,
            'isDbValue' => false
        );
    }

    /**
     * @param mixed $value
     * @throws DbFieldException
     */
    public function setValue($value) {
        if ($this->isVirtual() && $this->dbColumnConfig->importVirtualColumnValueFrom()) {
            throw new DbFieldException(
                $this,
                "Virtual field value [{$this->getName()}] cannot be set directly. " .
                    "Value is imported from field [{$this->dbColumnConfig->importVirtualColumnValueFrom()}]."
            );
        }
        $this->values['isset'] = true;
        $this->values['rawValue'] = $value;
        $this->values['value'] = $this->processNewValue($this->values['rawValue']);
        if ($this->isPk() && !isset($this->values['value'])) {
            // null pk value may cause non null violation in db - we don't need it to happen
            $this->resetValue();
        } else {
            $this->validate();
            if (!array_key_exists('dbValue', $this->values) || $this->values['dbValue'] !== $this->values['value']) {
                $this->values['isDbValue'] = false; //< value was updated
                $this->dbObject->fieldUpdated($this->getName());
            }
        }
    }

    /**
     * @return bool
     */
    public function hasValue() {
        if ($this->isVirtual() && $this->dbColumnConfig->importVirtualColumnValueFrom()) {
            return isset($this->dbObject->{$this->dbColumnConfig->importVirtualColumnValueFrom()});
        } else {
            return !empty($this->values['isset']);
        }
    }

    /**
     * @return bool
     * @throws DbFieldException
     */
    public function hasNotEmptyValue() {
        return $this->hasValue() && !empty($this->getValue());
    }

    /**
     * @return mixed|null
     * @throws DbFieldException
     */
    public function getValue() {
        if ($this->isVirtual() && $this->dbColumnConfig->importVirtualColumnValueFrom()) {
            return $this->dbObject->{$this->dbColumnConfig->importVirtualColumnValueFrom()};
        }
        if (!$this->hasValue() && $this->getName() !== $this->dbObject->_model->getPkColumn()) {
            // value not set and not a primary key
            if ($this->dbObject->exists()) {
                // on object update
                if ($this->isFile()) {
                    // import pk form object to create image urls and fill value
                    $this->importPkValue();
                } else {
                    // value is set in db but possibly was not fetched
                    // to avoid overwriting of correct value object must notify about this situation
                    $error = "Field [{$this->dbObject->_model->getAlias()}->{$this->getName()}]: value is not set. Possibly value was not fetched from DB";
                    throw new DbFieldException($this, $error);
                }
            } else {
                // on object create just set default value or null
                $this->setValue($this->getDefaultValueOr(null));
            }
        }
        return $this->hasValue() ? $this->values['value'] : null;
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
        return !empty($this->values['isDbValue']);
    }

    /**
     * @param bool $fromDb
     */
    public function setValueReceivedFromDb($fromDb = true) {
        if ($this->hasValue()) {
            $this->values['isDbValue'] = !!$fromDb;
            $this->values['dbValue'] = $this->values['value'];
        }
    }

    /**
     * @return bool
     */
    public function isValid() {
        return isset($this->values['error']);
    }

    /**
     * @return string|null
     */
    public function getValidationError() {
        return (isset($this->values['error'])) ? $this->values['error'] : null;
    }

    protected function setValidationError($error) {
        $this->values['error'] = $error;
    }

    /**
     * Imports db object's PK value to use it as field's value
     * @return null|string|int - pk value
     */
    protected function importPkValue() {
        $this->setValue($this->dbObject->pkValue());
    }

    /**
     * Analyze new value: validate data type and convert to field type
     * @param mixed $value
     * @return mixed
     * @throws DbFieldException
     */
    public function processNewValue($value) {
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
     * @throws DbFieldException
     */
    protected function doBasicValueValidationAndConvertion($value) {
        return $value;
    }

    /**
     * Validate field value using $this->canBeNull(), $this->required, $this->getType(), $this->validators
     * @param bool $silent - true: do not throw exception
     * @param bool $forSave - true: allow additional validations (like isUnique)
     * @return bool
     * @throws DbFieldException if $silent == false and value is invalid
     */
    public function validate($silent = true, $forSave = false) {
        unset($this->values['error']);
        // skip validation if value is not set or it is a db value (isDbValue is reliable enough to be used)
        if (empty($this->values['isset']) || !empty($this->values['isDbValue'])) {
            return true;
        }
        if (!$this->checkIfRequiredValueIsSet()) {
            $this->setValidationError('Field value is required');
        } else if (!$this->canBeNull() && !isset($this->values['value'])) {
            $this->setValidationError('Field value cannot be NULL');
        } else if (!$this->isValidValueLength()) {
            $this->setValidationError("Value [{$this->values['value']}] does not match required min-max length");
        } else if (!$this->isValidValueFormat($this->values['value'])) {
            if (empty($this->values['error'])) {
                $this->setValidationError("Value [{$this->values['value']}] is invalid");
            }
        } else if ($forSave && !$this->checkIfValueIsUnique()) {
            $this->setValidationError("Value [{$this->values['value']}] already exists in DB");
        } else {
            $this->runCustomValidators($silent, $forSave);
        }
        if (!empty($this->values['error']) && !$silent) {
            throw new DbFieldException($this, $this->values['error']);
        }
        return empty($this->values['error']);
    }

    /**
     * Check if field length does not exceeds $this->length
     * @return bool
     */
    protected function isValidValueLength() {
        $isValid = true;
        if (!empty($this->values['isset'])) {
            if ($this->getMinLength() > 0) {
                $isValid = mb_strlen($this->values['value']) >= $this->getMinLength();
            }
            if ($isValid && $this->getMaxLength() > 0) {
                $isValid = mb_strlen($this->values['value']) <= $this->getMaxLength();
            }
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
            $valid = isset($this->values['value']) && (!empty($this->values['value']) || is_bool($this->values['value']) || is_numeric($this->values['value']));
            // test if value is required for current action
            if (!$valid) {
                $valid = !$this->isRequiredOn($this->dbObject->exists(false) ? DbColumnConfig::ON_UPDATE : DbColumnConfig::ON_CREATE);
            }
        }
        return $valid;
    }

    /**
     * Test if value is unique
     * @return bool
     */
    protected function checkIfValueIsUnique() {
        $valid = true;
        if ($this->isUnique() && !empty($this->values['value'])) {
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
                $conditions[$this->dbObject->_model->getPkColumn() . '!='] = $this->dbObject->pkValue();
            }
            $valid = $this->dbObject->_model->count($conditions) == 0;
        }
        return $valid;
    }

    /**
     * Check if value has valid format
     * @param $value
     * @return bool
     * @throws DbFieldException
     */
    protected function isValidValueFormat($value) {
        return true;
    }

    /**
     * Apply custom validators
     * @param bool $silent - true: do not throw exception
     * @param bool $forSave - true: allow additional validations (like isUnique)
     * @return bool
     */
    protected function runCustomValidators($silent = true, $forSave = false) {
        //todo: implement custom validators
        return true;
    }

    /**
     * If this field can be skipped when value is not set and not requred
     * @param bool $skipIfDbValue - true: if $this->isValueReceivedFromDb() == true - it will be skipped
     * @return bool
     */
    public function canBeSkipped($skipIfDbValue = false) {
        if (
            ($skipIfDbValue && $this->isValueReceivedFromDb())
            || $this->isVirtual()
            || $this->isExcludedOn($this->dbObject->exists() ? DbColumnConfig::ON_UPDATE : DbColumnConfig::ON_CREATE)
        ) {
            return true;
        } else {
            // skip on create/update when not set and not required
            return !$this->hasValue() && $this->checkIfRequiredValueIsSet();
        }
    }

}