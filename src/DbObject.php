<?php

namespace PeskyORM;
use PeskyORM\DbColumnConfig;
use PeskyORM\DbObjectField\FileField;
use PeskyORM\DbObjectField\ImageField;
use PeskyORM\DbObjectField\TimestampField;
use PeskyORM\Exception\DbExceptionCode;
use PeskyORM\Exception\DbFieldException;
use PeskyORM\Exception\DbObjectException;
use PeskyORM\Lib\File;
use PeskyORM\Lib\Folder;
use PeskyORM\Lib\ImageUtils;
use PeskyORM\Lib\StringUtils;
use PeskyORM\Lib\Utils;

/**
 * Class DbObject
 */
class DbObject {

    /**
     * @var bool
     * true: do not delete attached files in DbObject->delete()
     */
    protected $_dontDeleteFiles = false;

    /**
     * Associative list that maps field names with objects that extend DbObjectField class
     * @var DbObjectField[]
     */
    protected $_fields = array();
    /**
     * @var array
     */
    protected $_allFieldNames = array();
    /**
     * Associative list that maps related object alias to its class name
     * @var string[]
     */
    protected $_relationAliasToClassName = array();
    /**
     * associative list that maps related object alias to relation join conditions
     * @var array
     */
    protected $_aliasToJoinConditions = array();

    /**
     * associative list that maps related object aliases to their DbObject (has one / belongs to) or array of DbObject (has many)
     * @var DbObject[]|array[DbObject]
     */
    protected $_relatedObjects = array();
    // todo: assign this object into relations of related objects to implement parent<->child links

    /**
     * @var DbModel
     */
    protected $_model;
    /**
     * @var array
     */
    protected $_customErrors = array();

    /**
     * Enable/Disable storing of field updates
     * Used in DbObject->save() to prevent storing some fields in DbObject->$_updatedFields
     * @var bool
     */
    private $_allowFieldsUpdatesTracking = true;
    /**
     * List of fields updated after DbObject->begin() was called
     * Used DbObject->begin(), DbObject->commit(), DbObject->rollback()
     * @var array
     */
    private $_updatedFields = array();
    /**
     * Indicates if field updates need to be stored into DbObject->$_updatedFields
     * Used in DbObject->begin(), DbObject->commit(), DbObject->rollback()
     * @var bool
     */
    private $_isStoringFieldUpdates = false;
    /**
     * Values of fields before DbObject->begin() was called. This values will be restored when DbObject->rollback() is called
     * Used in DbObject->begin(), DbObject->commit(), DbObject->rollback()
     * @var null|array
     */
    private $_dataBackup = null;
    /**
     * Used by forms to remember some data before it was changed
     * Use case: form allows changing of primary key but save() requires old pk value to manage changes
     * @var null|array
     */
    private $_originalData = null;
    /**
     * Key name in $data array passed to _fromData()
     * @var string
     */
    protected $_originalDataKey = '_backup';

    static public $extToConetntType = array(
        'mp4' => 'video/mp4',
        'mov' => 'video/quicktime',
    );

    /**
     * @param DbModel $model
     * @param null|array|string|int $data - null: do nothing | int and string: is primary key (read db) | array: object data
     * @param bool $filter - used only when $data not empty and is array
     *      true: filters $data that does not belong t0o this object
     *      false: $data that does not belong to this object will trigger exceptions
     * @param bool $isDbValues - true: indicates that field values passsed via $data as array are db values
     * @throws DbObjectException
     */
    public function __construct(DbModel $model, $data = null, $filter = false, $isDbValues = false) {
        $this->_model = $model;
        $this->_dontDeleteFiles = !!$this->_dontDeleteFiles;
        // initiate DbObjectField for all fields
        $nameSpace = __NAMESPACE__ . '\\DbObjectField';
        foreach ($this->_model->getTableColumns() as $name => $dbColumnConfig) {
            $fieldClass = $dbColumnConfig->getClassName($nameSpace);
            $this->_allFieldNames[] = $name;
            $this->_fields[$name] = new $fieldClass($this, $dbColumnConfig);
        }
        // test if primary key field provided
        if (!$this->_hasPkField()) {
            throw new DbObjectException($this, "Primary key field [{$this->_getPkFieldName()}] not found in Model->fields");
        }
        // initiate related objects
        /** @var DbRelationConfig $settings */
        foreach ($this->_model->getTableRealtaions() as $alias => $settings) {
            $this->_aliasToJoinConditions[$alias] = $this->_buildRelationJoinConditions($alias, $settings);
            $this->_relationAliasToClassName[$alias] = $this->_model->getFullDbObjectClass($settings->getForeignTable());
        }
        $this->_cleanRelatedObjects();
        // set values if possible
        if (!empty($data)) {
            if (is_array($data)) {
                $this->_fromData($data, !empty($filter), $isDbValues);
            } else {
                $this->read($data);
            }
        }
    }

    /**
     * @return \PeskyORM\DbTableConfig
     */
    public function _getTableConfig() {
        return $this->_getModel()->getTableConfig();
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function _hasField($fieldName) {
        return is_string($fieldName) && !empty($this->_fields[$fieldName]);
    }

    /**
     * @param $fieldName
     * @return DbObjectField
     * @throws DbObjectException
     */
    public function _getField($fieldName) {
        if (!$this->_hasField($fieldName)) {
            throw new DbObjectException($this, "Db object has no field called [{$fieldName}]");
        }
        return $this->_fields[$fieldName];
    }

    /**
     * @return bool
     */
    public function _hasPkField() {
        return $this->_getModel()->hasPkColumn() && $this->_hasField($this->_getPkFieldName());
    }

    /**
     * @return DbObjectField
     * @throws DbObjectException
     */
    public function _getPkField() {
        if (!$this->_hasPkField()) {
            throw new DbObjectException($this, "Db object has no primary key field called [{$this->_getPkFieldName()}]");
        }
        return $this->_getField($this->_getPkFieldName());
    }

    /**
     * @return mixed
     */
    public function _getPkFieldName() {
        return $this->_getModel()->getPkColumn();
    }

    /**
     * @return mixed
     */
    public function _getPkValue() {
        return $this->_getPkField()->getValue(null);
    }

    /**
     * @param string $alias
     * @return bool
     */
    public function _hasRelation($alias) {
        return is_string($alias) && $this->_getTableConfig()->hasRelation($alias);
    }

    /**
     * @param $alias
     * @return DbRelationConfig
     * @throws \PeskyORM\Exception\DbTableConfigException
     */
    public function _getRelationConfig($alias) {
        return $this->_getTableConfig()->getRelation($alias);
    }

    /**
     * @return bool
     */
    public function _hasFileFields() {
        return $this->_getTableConfig()->hasFileColumns();
    }

    /**
     * @param $fieldName
     * @return bool
     */
    public function _hasFileField($fieldName) {
        return $this->_getTableConfig()->hasFileColumn($fieldName);
    }

    /**
     * @param $fieldName
     * @return FileField|ImageField
     * @throws DbObjectException
     */
    public function _getFileField($fieldName) {
        if (!$this->_hasFileField($fieldName)) {
            throw new DbObjectException($this, "Db object has no file-field called [{$fieldName}]");
        }
        return $this->_getField($fieldName);
    }

    /**
     * @return \PeskyORM\DbColumnConfig[]
     */
    public function _getFileFields() {
        return $this->_getTableConfig()->getFileColumns();
    }

    /**
     * @return array|null
     */
    public function _getOriginalData() {
        return $this->_originalData;
    }

    /**
     * @param $data
     */
    public function _setOriginalData($data) {
        $this->_originalData = $data;
    }

    /**
     * @return DbModel
     */
    public function _getModel() {
        return $this->_model;
    }

    /**
     * @return string
     */
    public function _getModelAlias() {
        return $this->_getModel()->getAlias();
    }

    /**
     * @param string $alias
     * @return string
     */
    protected function _getLocalFieldNameForRelation($alias) {
        return $this->_getRelationConfig($alias)->getColumn();
    }

    /**
     * @param string $alias
     * @return string
     */
    protected function _getForeignFieldNameForRelation($alias) {
        return $this->_getRelationConfig($alias)->getForeignColumn();
    }

    /**
     * @param string $alias
     * @return string
     */
    protected function _getForeignTableForRelation($alias) {
        return $this->_getRelationConfig($alias)->getForeignTable();
    }

    /**
     * @param string $alias
     * @return string
     */
    protected function _getTypeOfRealation($alias) {
        return $this->_getRelationConfig($alias)->getType();
    }

    /**
     * Build conditions similar to JOIN ON conditions
     * @param string $relationAlias
     * @param DbRelationConfig $relationSettings
     * @return array
     */
    protected function _buildRelationJoinConditions($relationAlias, DbRelationConfig $relationSettings) {
        $conditions = $this->_getModel()->getAdditionalConditionsForRelation($relationAlias);
        $conditions[$relationAlias . '.' . $relationSettings->getForeignColumn()] = $this->_getModelAlias() . '.' . $relationSettings->getColumn();
        return $conditions;
    }

    /**
     * Insert values into conditions instead of "$this->alias"."field_name" and "field_name"
     * @param $conditions
     * @return array
     */
    protected function _insertDataIntoRelationConditions($conditions) {
        $dbObject = $this;
        $replacer = function ($matches) use ($dbObject) {
            if ($dbObject->_hasField($matches[2])) {
                return $dbObject->{$matches[2]};
            } else {
                return $matches[0];
            }
        };
        $newConditions = array();
        foreach ($conditions as $key => $value) {
            $key = preg_replace_callback("%`?({$this->_getModelAlias()})`?\.`?([a-zA-Z0-9_]+)`?%is", $replacer, $key);
            $key = preg_replace_callback("%(?:^|\s)(`?)([a-zA-Z0-9_]+)`?%is", $replacer, $key);
            $value = preg_replace_callback("%`?({$this->_getModelAlias()})`?\.`?([a-zA-Z0-9_]+)`?%is", $replacer, $value);
            $value = preg_replace_callback("%(?:^|\s)(`?)([a-zA-Z0-9_]+)`?%is", $replacer, $value);
            $newConditions[$key] = $value;
        }
        return $newConditions;
    }

    /**
     * Clean 1 related objects
     * @param string $alias
     * @throws DbObjectException
     */
    protected function _cleanRelatedObject($alias) {
        if (!$this->_hasRelation($alias)) {
            throw new DbObjectException($this, "Unknown relation with alias [$alias]");
        }
        $this->_relatedObjects[$alias] = false;
    }

    /**
     * Clean all related objects
     */
    protected function _cleanRelatedObjects() {
        foreach ($this->_getModel()->getTableRealtaions() as $alias => $settings) {
            $this->_relatedObjects[$alias] = false;
        }
    }

    /**
     * @param string $alias
     * @param bool $initIfNotCreated - true: will call $this->_initRelatedObject($alias) if related object not created yet
     * @return DbObject
     * @throws DbObjectException
     */
    private function _getRelatedObject($alias, $initIfNotCreated = true) {
        if ($this->_relatedObjects[$alias] === false && $initIfNotCreated) {
            $this->_initRelatedObject($alias);
        }
        return $this->_relatedObjects[$alias];
    }

    /**
     * Init related object by $alias
     * @param string $relationAlias
     * @param null|DbObject|DbObject[] $objectOrDataOrPkValue
     * @param bool $ignorePkNotSetError - true: exception '[local_field] is empty' when local_field is primary key will be ignored
     * @return bool|$this[]|$this - false: for hasMany relation
     * @throws DbObjectException
     */
    protected function _initRelatedObject($relationAlias, $objectOrDataOrPkValue = null, $ignorePkNotSetError = false) {
        if (!$this->_hasRelation($relationAlias)) {
            throw new DbObjectException($this, "Unknown relation with alias [$relationAlias]");
        }
        $localField = $this->_getLocalFieldNameForRelation($relationAlias);
        if (!$this->_hasField($localField)) {
            throw new DbObjectException($this, "Relation [$relationAlias] points to unknown field [{$localField}]");
        }
        // check if passed object is valid
        if ($this->_getTypeOfRealation($relationAlias) === DbRelationConfig::HAS_MANY) {
            $this->_initOneToManyRelation($relationAlias, $objectOrDataOrPkValue, $ignorePkNotSetError);
        } else {
            $this->_initOneToOneRelation($relationAlias, $objectOrDataOrPkValue, $ignorePkNotSetError);
        }
        return $this->_relatedObjects[$relationAlias];
    }

    /**
     * @param string $relationAlias
     * @param array|DbObject|null $objectOrDataOrPkValue
     * @param bool $ignorePkNotSetError
     * @throws DbObjectException
     */
    protected function _initOneToOneRelation($relationAlias, $objectOrDataOrPkValue = null, $ignorePkNotSetError = false) {
        $localFieldName = $this->_getLocalFieldNameForRelation($relationAlias);
        $relationFieldName = $this->_getForeignFieldNameForRelation($relationAlias);
        $localFieldIsPrimaryKey = $localFieldName === $this->_getPkFieldName();
        if ($this->_isFieldHasEmptyValue($localFieldName)) {
            $relationFieldValue = false;
            if (is_array($objectOrDataOrPkValue) && !empty($objectOrDataOrPkValue[$relationFieldName])) {
                $relationFieldValue = $objectOrDataOrPkValue[$relationFieldName];
            } else if (is_object($objectOrDataOrPkValue) && !empty($objectOrDataOrPkValue->$relationFieldName)) {
                $relationFieldValue = $objectOrDataOrPkValue->$relationFieldName;
            }
            if (!empty($relationFieldValue)) {
                $this->_setFieldValue($localFieldName, $relationFieldValue);
            } else if ($localFieldIsPrimaryKey || $this->_getField($localFieldName)->isRequiredOnAnyAction()) {
                // both fields are empty and is required or is primary key
                throw new DbObjectException($this, "Cannot link [{$this->_getModelAlias()}] with [{$relationAlias}]: [{$this->_getModelAlias()}->{$localFieldName}] and {$relationAlias}->{$relationFieldName} are empty");
            }
        }
        if (is_object($objectOrDataOrPkValue)) {
            $objectClass = get_class($objectOrDataOrPkValue);
            if ($objectClass !== $this->_relationAliasToClassName[$relationAlias]) {
                throw new DbObjectException($this, "Trying to assign object of class [$objectClass] as object of class [{$this->_relationAliasToClassName[$relationAlias]}]");
            }
            $relatedObject = $objectOrDataOrPkValue;
        } else {
            $relatedObject = $this->_getModel()->getDbObject($this->_getForeignTableForRelation($relationAlias));
            if (empty($objectOrDataOrPkValue)) {
                $this->linkRelatedObjectToThis($relationAlias, $relatedObject);
            } else {
                if (is_array($objectOrDataOrPkValue)) {
                    $relatedObject->_fromData($objectOrDataOrPkValue);
                } else if ($relatedObject->_getPkField()->isValidValueFormat($objectOrDataOrPkValue)) {
                    $relatedObject->read($objectOrDataOrPkValue);
                } else {
                    throw new DbObjectException($this, "Cannot set values of related object [$relationAlias]. Values must be array or pk value.");
                }
                $valid = $this->validateRelationData($relationAlias, $ignorePkNotSetError);
                if ($valid === false) {
                    throw new DbObjectException($this, "Related object [$relationAlias] does not belong to this object");
                } else if ($valid === null && $relatedObject->_isFieldHasEmptyValue($relationFieldName)) {
                    $this->linkRelatedObjectToThis($relationAlias, $relatedObject);
                }
            }
        }
        $this->_relatedObjects[$relationAlias] = $relatedObject;
    }

    protected function _initOneToManyRelation($relationAlias, $objectOrDataOrPkValue, $ignorePkNotSetError = false) {
        // todo: implement DbObjectCollection that works as usual array but has some useful methods like find/sort/filter
        $localFieldName = $this->_getLocalFieldNameForRelation($relationAlias);
        $relationFieldName = $this->_getForeignFieldNameForRelation($relationAlias);
        if ($this->_isFieldHasEmptyValue($localFieldName)) {
            throw new DbObjectException($this, "Cannot link [{$this->_getModelAlias()}] with [{$relationAlias}]: [{$this->_getModelAlias()}->{$localFieldName}] is empty");
        }
        if (empty($objectOrDataOrPkValue)) {
            $relatedObjects = false; //< means not loaded
        } else if (!is_array($objectOrDataOrPkValue) || !isset($objectOrDataOrPkValue[0])) {
            throw new DbObjectException($this, "Related objects must be a plain array");
        } else {
            // array of related objects
            $relatedObjects = array();
            foreach ($objectOrDataOrPkValue as $index => $item) {
                if (empty($item)) {
                    throw new DbObjectException($this, "One of related objects (with index{$index}) is empty");
                } else if (is_object($item)) {
                    $this->_validateRelatedObjectClass($item, $relationAlias);
                    $relatedObjects[] = $item;
                } else {
                    // array of item data arrays or item ids
                    $relatedObjects[] = $this->_getModel()->getDbObject($this->_getForeignTableForRelation($relationAlias), $item);
                }
            }
            // validate relation
            $valid = $this->validateRelationData($relationAlias, $ignorePkNotSetError);
            if ($valid === false) {
                throw new DbObjectException($this, "Related object [$relationAlias] does not belong to this object");
            } else if ($valid === null) {
                /** @var DbObject $relatedObject */
                foreach ($relatedObjects as $relatedObject) {
                    if ($relatedObject->_isFieldHasEmptyValue($relationFieldName)) {
                        $this->linkRelatedObjectToThis($relationAlias, $relatedObject);
                    }
                }
            }

        }
        $this->_relatedObjects = $relatedObjects;
    }

    /**
     * @param DbObject $relatedObject
     * @param string $relationAlias
     * @throws DbObjectException
     */
    private function _validateRelatedObjectClass(DbObject $relatedObject, $relationAlias) {
        $objectClass = get_class($relatedObject);
        if ($objectClass !== $this->_relationAliasToClassName[$relationAlias]) {
            throw new DbObjectException($this, "Trying to assign object of class [$objectClass] as object of class [{$this->_relationAliasToClassName[$relationAlias]}]");
        }
    }

    /**
     * @param $alias
     * @param DbObject $relatedObject
     * @throws DbFieldException
     * @throws DbObjectException
     */
    protected function linkRelatedObjectToThis($alias, DbObject $relatedObject) {
        $localField = $this->_getField($this->_getLocalFieldNameForRelation($alias));
        if ($localField->hasNotEmptyValue()) {
            $relationFieldName = $this->_getForeignFieldNameForRelation($alias);
            $relatedObject->_getField($relationFieldName)->setDefaultValue($localField->getValue());
            $relatedObject->_setFieldValue($relationFieldName, $localField->getValue());
        }
    }

    /**
     * Fill fields using passed $data array + mark all passed field values as updates to db values if $this->exists()
     * 1. If $this->pkValue() empty or not passed in $data or they are no equal - $this->reset() is called
     * 2. If $this->pkValue() is not empty, passed in $data and they are equal - loaded object will be updated by
     *      fields from $data, while other fields (not present in $data) will remain untouched
     * @param array $data
     * @param bool|array $filter -
     *      true: filters data that does not belong to this object
     *      false: data that does not belong to this object will trigger exceptions
     *      array: list of fields to use
     * @return $this
     * @throws DbObjectException when $filter == false and unknown field detected in $data
     */
    public function fromData($data, $filter = false) {
        $this->_fromData($data, $filter, false);
        return $this;
    }

    /**
     * Update some field values without resetting loaded object.
     * Primary key value may be omitted in $data
     * Can work if $this->exists() == false, pk just not imported
     * @param array $values
     * @param bool|array $filter -
     *      true: filters data that does not belong to this object
     *      false: data that does not belong to this object will trigger exceptions
     *      array: list of fields to use
     * @return $this
     * @throws DbObjectException when $filter == false and unknown field detected in $data
     */
    public function updateValues($values, $filter = false) {
        if ($this->exists()) {
            $values[$this->_getPkFieldName()] = $this->_getPkValue();
        }
        $this->_fromData($values, $filter, false);
        return $this;
    }

    /**
     * Update fields from $data and mark them as they are gathered from db (used in save())
     * @param array $data
     * @return $this
     */
    protected function _updateWithDbValues($data) {
        foreach ($data as $key => $value) {
            $this->_getField($key)->setValue($value, true);
        }
        return $this;
    }

    /**
     * Clean current fields and fill them using passed $data array + mark all passed field values as db values
     * @param array $data
     * @param bool|array $filter -
     *      true: filters data that does not belong to this object
     *      false: data that does not belong to this object will trigger exceptions
     *      array: list of fields to use
     * @return $this
     * @throws DbObjectException when $filter == false and unknown field detected in $data
     */
    public function fromDbData($data, $filter = false) {
        $this->_fromData($data, $filter, true);
        return $this;
    }

    /**
     * Clean current fields and fill them using passed $data array
     * @param array $data
     * @param bool|array $filter -
     *      true: filters data that does not belong to this object
     *      false: data that does not belong to this object will trigger exceptions
     *      array: list of fields to use
     * @param bool $isDbValue - true: all values are updates fro db values | false: all values are db values
     * @return $this
     * @throws DbObjectException when $filter == false and unknown field detected in $data
     */
    protected function _fromData($data, $filter = false, $isDbValue = false) {
        // reset db object values when:
        // 1. object has no pk value
        // 2. $data does not contain pk value
        // 3. object's pk value does not match $data pk value
        // otherwise $data treated as updates to existing db object
        $pkField = $this->_getPkField();
        if (
            !$this->exists()
            || empty($data[$pkField->getName()]) //< pk value cannot be empty string. if it can - you have a problem
            || $data[$pkField->getName()] != $pkField->getValue()
        ) {
            $this->reset();
        }
        if (is_array($data)) {
            // remember original data
            if (!empty($data[$this->_originalDataKey])) {
                $this->_setOriginalData(json_decode($data[$this->_originalDataKey], true));
                unset($data[$this->_originalDataKey]);
            }
            // filter fields
            if (is_array($filter)) {
                $data = array_intersect_key($data, array_flip($filter));
                $filter = false;
            }
            // set primary key first
            if (isset($data[$pkField->getName()])) {
                $pkField->setValue($data[$pkField->getName()], $isDbValue);
                unset($data[$pkField->getName()]);
            }
            foreach ($data as $fieldNameOrAlias => $value) {
                if ($this->_hasField($fieldNameOrAlias)) {
                    $this->_getField($fieldNameOrAlias)->setValue($value, $isDbValue);
                } else if ($this->_hasRelation($fieldNameOrAlias) && is_array($value)) {
                    if ($this->_hasRelatedObject($fieldNameOrAlias)) {
                        $this->_getRelatedObject($fieldNameOrAlias)->updateValues($value);
                    } else {
                        $this->_initRelatedObject($fieldNameOrAlias, $data, true);
                    }
                } else if (!$filter && $fieldNameOrAlias[0] !== '_') {
                    $class = get_class($this);
                    throw new DbObjectException($this, "Unknown field [$fieldNameOrAlias] detected in [$class]");
                }
            }
        } else if ($data !== false) {
            $class = get_class($this);
            throw new DbObjectException($this, "Invalid data passed to [$class] (details in browser console)");
        }
    }

    /**
     * Clean all data fields (set default values)
     * @return $this
     */
    public function reset() {
        $this->_customErrors = array();
        $this->_setOriginalData(null);
        $this->_isStoringFieldUpdates = false;
        foreach ($this->_fields as $dbField) {
            $dbField->resetValue();
        }
        $this->_dataBackup = null;
        $this->_updatedFields = array();
        $this->_cleanRelatedObjects();
        return $this;
    }

    /**
     * Validate passed $fields or all fields if $fields is empty
     * @param null|string|array $fieldNames - empty: all fields | string: single field | array: only this fields
     * @param bool $forSave - true: allows some specific validations lise isUnique
     * @return array - validation errors
     */
    public function validate($fieldNames = null, $forSave = false) {
        if (empty($fieldNames) || (!is_array($fieldNames) && !is_string($fieldNames))) {
            $fieldNames = $this->_allFieldNames;
        } else if (is_string($fieldNames)) {
            $fieldNames = array($fieldNames);
        }
        $errors = array();
        foreach ($fieldNames as $fieldName) {
            $field = $this->_getField($fieldName);
            if (!$field->validate(true, $forSave)) {
                $errors[$fieldName] = $field->getValidationError();
            }
        }
        return $errors;
    }

    /**
     * Sets or gets field value.
     * Used for trailing sets
     * @param string $fieldNameOrAlias
     * @param array $args
     * @return $this|mixed
     * @throws DbObjectException
     */
    public function __call($fieldNameOrAlias, $args) {
        if (preg_match('%^set(.+)$%', $fieldNameOrAlias, $matches)) {
            $fieldNameOrAlias = $matches[1];
        } else {
            throw new DbObjectException($this, "Magic method [{$fieldNameOrAlias}()] is forbiden. You can magically call only methods starting with 'set', for example: setId(1)");
        }
        if (count($args) !== 1) {
            throw new DbObjectException($this, "Magic method [{$fieldNameOrAlias}()] accepts only 1 argument, but " . count($args) . ' arguments passed');
        }
        $fieldName = StringUtils::underscore($fieldNameOrAlias);
        if ($this->_hasField($fieldName)) {
            return $this->_setFieldValue($fieldName, $args[0]);
        } else if ($this->_hasRelation($fieldNameOrAlias)) {
            return $this->_initRelatedObject($fieldNameOrAlias, $args[0]);
        } else {
            throw new DbObjectException($this, "Unknown DbObject field or relation alias [$fieldNameOrAlias]");
        }
    }

    /**
     * @param string $fieldName
     * @return mixed|null
     * @throws DbFieldException
     * @throws DbObjectException
     */
    public function _getFieldValue($fieldName) {
        return $this->_getField($fieldName)->getValue();
    }

    /**
     * @param string $fieldName
     * @param mixed $newValue
     * @param bool $isDbValue
     * @return $this
     * @throws DbFieldException
     * @throws DbObjectException
     */
    public function _setFieldValue($fieldName, $newValue, $isDbValue = false) {
        $this->_getField($fieldName)->setValue($newValue, $isDbValue);
        return $this;
    }

    /**
     * @param string $fieldName
     * @return $this
     * @throws DbObjectException
     */
    public function _unsetFieldValue($fieldName) {
        $this->_getField($fieldName)->resetValue();
        return $this;
    }

    /**
     * @param string $fieldName
     * @return bool
     * @throws DbObjectException
     */
    public function _isFieldHasEmptyValue($fieldName) {
        return !$this->_getField($fieldName)->hasNotEmptyValue();
    }

    /**
     * Get value of db field
     * @param string $fieldNameOrAlias
     * @return mixed|DbObject|DbObject[]
     * @throws DbObjectException
     */
    public function __get($fieldNameOrAlias) {
        if ($this->_hasField($fieldNameOrAlias)) {
            return $this->_getFieldValue($fieldNameOrAlias);
        } else if ($this->_hasRelation($fieldNameOrAlias)) {
            // related object
            if ($this->_relatedObjects[$fieldNameOrAlias] === false) {
                // Related Object not loaded
                $this->_findRelatedObject($fieldNameOrAlias);
            }
            return $this->_relatedObjects[$fieldNameOrAlias];
        } else if (
            preg_match('%^(.*)_(path|exists)$%', $fieldNameOrAlias, $matches)
            && $this->_hasFileField($matches[1])
        ) {
            // field name looks like "file_path" or "file_exists" and field "file" exists in object
            $field = $this->_getFileField($matches[1]);
            switch($matches[2]) {
                case 'exists':
                    return $field->isFileExists();
                    break;
                case 'path':
                    return $field->getFilePath();
                    break;
            }
        } else if (
            preg_match('%^(.*)_(date|time|ts)$%is', $fieldNameOrAlias, $matches)
            && $this->_hasField($matches[1])
            && $this->_getField($matches[1]) instanceof TimestampField
        ) {
            /** @var TimestampField $field */
            $field = $this->_getField($matches[1]);
            switch($matches[2]) {
                case 'date':
                    return $field->getDate();
                    break;
                case 'time':
                    return $field->getTime();
                    break;
                case 'ts':
                    return $field->getUnixTimestamp();
                    break;
            }
        } else {
            throw new DbObjectException($this, "Unknown DbObject field or relation alias [$fieldNameOrAlias]");
        }
        return null;
    }

    /**
     * @return array
     */
    public function getValidationErrors() {
        $errors = array();
        foreach ($this->_fields as $fieldName => $dbField) {
            if (!$dbField->isValid()) {
                $errors[$fieldName] = $dbField->getValidationError();
            }
        }
        // add custom errors
        if (!empty($this->_customErrors)) {
            if (empty($errors)) {
                $errors = $this->_customErrors;
            } else if (is_array($this->_customErrors)) {
                $errors = array_merge($errors, $this->_customErrors);
            } else {
                $errors[] = $this->_customErrors;
            }
        }
        foreach ($this->_relatedObjects as $alias => $object) {
            if (is_object($object)) {
                $relatedErrors = $object->getValidationErrors();
                if (!empty($relatedErrors)) {
                    $errors[$alias] = $relatedErrors;
                }
            } else if (is_array($object)) {
                /** @var DbObject $realObject */
                foreach ($object as $index => $realObject) {
                    $relatedErrors = $realObject->getValidationErrors();
                    if (!empty($relatedErrors)) {
                        if (empty($errors[$alias])) {
                            $errors[$alias] = array();
                        }
                        $errors[$alias][$index] = $relatedErrors;
                    }
                }
            }
        }
        return $errors;
    }

    /**
     * Unset field value (accepts multiple field names)
     * @param string $fieldNameOrAlias
     */
    public function __unset($fieldNameOrAlias) {
        if ($this->_hasField($fieldNameOrAlias)) {
            $field = $this->_getField($fieldNameOrAlias);
            $field->resetValue();
            // unset linked relations
            foreach ($field->getRelations() as $relationAlias) {
                $this->_cleanRelatedObject($relationAlias);
            }
        } else if ($this->_hasRelation($fieldNameOrAlias)) {
            $this->_cleanRelatedObject($fieldNameOrAlias);
        }
    }

    /**
     * Check if field value is set
     * @param string $fieldNameOrAlias - field name or related object alias
     * @return bool
     * @throws DbObjectException
     */
    public function __isset($fieldNameOrAlias) {
        if ($this->_hasField($fieldNameOrAlias)) {
            return $this->_getField($fieldNameOrAlias)->hasValue();
        } else if ($this->_hasRelation($fieldNameOrAlias)) {
            return $this->_hasRelatedObject($fieldNameOrAlias);
        } else {
            throw new DbObjectException($this, "Unknown DbObject field or relation alias [$fieldNameOrAlias]");
        }
    }

    /**
     * @param string $alias
     * @return bool
     */
    protected function _hasRelatedObject($alias) {
        $relation = $this->_getRelationConfig($alias);
        $relatedObject = $this->_relatedObjects[$alias];
        if ($relation->getType() === DbRelationConfig::HAS_MANY) {
            return $relatedObject !== false;
        } else {
            // 1:1 relation
            if ($relatedObject->exists()) {
                return true;
            } else {
                // todo: is this really needed? what situation is this?
                $localField = $this->_getField($relation->getColumn());
                $foreignField = $relatedObject->_getField($relation->getForeignColumn());
                $bothFieldsHasValues = $localField->hasValue() && $foreignField->hasValue();
                return $bothFieldsHasValues && $localField->getValue() === $foreignField->getValue();
            }
        }
    }

    /**
     * Start recording field values changing
     * @param bool|array|string $withRelations
     *      - true: all relations
     *      - false: do not commit relations
     *      - string: relation
     *      - array: list of relations
     * @return $this
     */
    public function begin($withRelations = false) {
        $this->_updatedFields = array();
        $this->_isStoringFieldUpdates = true;
        $this->_dataBackup = $this->getFieldsValues();
        $this->runActionForRelations($withRelations, 'begin');
        return $this;
    }

    /**
     * Commit field values
     * @param bool|array|string $commitRelations
     *      - true: all beginned relations (if none - all relations)
     *      - false: do not commit relations
     *      - string: relation
     *      - array: list of relations
     * @return bool
     * @throws DbObjectException
     */
    public function commit($commitRelations = false) {
        $ret = true;
        if (!empty($this->_updatedFields)) {
            $ret = $this->saveUpdates($this->_updatedFields);
        }
        if ($ret) {
            $this->_updatedFields = array();
            $this->_isStoringFieldUpdates = false;
            $this->_dataBackup = null;
            $ret = $this->runActionForRelations($commitRelations, 'commit');
        }
        return $ret;
    }

    /**
     * Restore updated field values to the values they had before begin()
     * @param bool|array|string $rollbackRelations
     *      - true: all beginned relations (if none - all relations)
     *      - false: do not commit relations
     *      - string: relation
     *      - array: list of relations
     * @return $this
     */
    public function rollback($rollbackRelations = false) {
        // restore db object state before begin()
        $this->_isStoringFieldUpdates = false;
        foreach ($this->_updatedFields as $fieldName) {
            if (array_key_exists($fieldName, $this->_dataBackup)) {
                $this->_setFieldValue($fieldName, $this->_dataBackup[$fieldName]);
            } else {
                $this->_unsetFieldValue($fieldName);
            }
        }
        $this->_updatedFields = array();
        $this->_dataBackup = null;
        $this->runActionForRelations($rollbackRelations, 'commit');
        return $this;
    }

    protected $_relationsBeginned = array();
    /**
     * Collect and validate relations list and run begin(), commit() or rollback() method
     * @param array|string|bool $relations
     *      - false: no relations
     *      - true: all realtions for begin() or all beginned relations for commit() and rollback()
     *      - string: 1 relation
     *      - array: list of relations
     * @param string $action
     * @return bool - is action was successfull
     * @throws DbObjectException
     */
    protected function runActionForRelations($relations, $action) {
        if (!empty($relations) && in_array($action, array('commit', 'begin', 'rollback', 'save'))) {
            $cleanAll = false;
            $isSaveAction = in_array($action, array('commit', 'save'));
            // collect relations
            if (is_string($relations)) {
                if (!in_array($relations, array_keys($this->_relationAliasToClassName))) {
                    throw new DbObjectException($this, "Unknown relation [{$relations}] in [{$this->_getModelAlias()}]");
                }
                $relations = array($relations);
            } else if (is_array($relations)) {
                $diff = array();
                foreach ($relations as $relationAlias) {
                    if (!array_key_exists($relationAlias, $this->_relationAliasToClassName)) {
                        $diff[] = $relationAlias;
                    }
                }
                if (!empty($diff)) {
                    $unknown = '[' . implode('], [', $diff) . ']';
                    throw new DbObjectException($this, "Unknown relations $unknown in [{$this->_getModelAlias()}]");
                }
            } else if ($action == 'begin') {
                $relations = array_keys($this->_relationAliasToClassName);
            } else if (in_array($action, array('commit', 'rollback'))) {
                if (empty($this->_relationsBeginned)) {
                    return true; //< nothing to commit or roll back
                } else {
                    $relations = $this->_relationsBeginned;
                }
                $cleanAll = true;
            } else {
                // all relations
                $relations = array_keys($this->_relationAliasToClassName);
            }
            if ($action == 'begin') {
                $this->_relationsBeginned = $relations;
            }
            $success = true;
            // perform action on all collected relations
            foreach ($relations as $relationAlias) {
                if (empty($this->_relatedObjects[$relationAlias])) {
                    continue;
                }
                $localFieldName = $this->_getLocalFieldNameForRelation($relationAlias);
                if (!isset($localFieldName)) {
                    throw new DbObjectException($this, "Unknown relation with alias [$relationAlias]");
                } else if (empty($this->$localFieldName)) {
                    throw new DbObjectException($this, "Cannot link [{$this->_getModelAlias()}] with [{$relationAlias}]: [{$this->_getModelAlias()}->{$localFieldName}] is empty");
                }
                $foreignFieldName = $this->_getForeignFieldNameForRelation($relationAlias);
                if (is_array($this->_relatedObjects[$relationAlias])) {
                    /** @var DbObject $object */
                    foreach ($this->_relatedObjects[$relationAlias] as $object) {
                        if ($isSaveAction && $foreignFieldName != $object->_getPkFieldName()) {
                            $object->$foreignFieldName($this->$localFieldName);
                        }
                        $ret = $object->$action();
                        if ($isSaveAction && !$ret) {
                            $success = false;
                        }
                    }
                } else if (is_object($this->_relatedObjects[$relationAlias])) {
                    if ($isSaveAction) {
                        if (
                            !empty($this->_relatedObjects[$relationAlias]->$foreignFieldName)
                            && $this->_relatedObjects[$relationAlias]->$foreignFieldName !== $this->$localFieldName
                        ) {
                            throw new DbObjectException($this, "Trying to attach [$relationAlias] that already attached to another [{$this->_getModelAlias()}]");
                        }
                        $this->_relatedObjects[$relationAlias]->$foreignFieldName($this->$localFieldName);
                    }
                    if ($action === 'save') {
                        $ret = $this->_relatedObjects[$relationAlias]->save(true, true);
                    } else {
                        $ret = $this->_relatedObjects[$relationAlias]->$action();
                    }
                    if ($isSaveAction && !$ret) {
                        $success = false;
                    }
                }
            }
            if (!$success) {
                return false;
            }
            if ($action != 'begin') {
                if ($cleanAll) {
                    $this->_relationsBeginned = array();
                } else {
                    $this->_relationsBeginned = array_diff($this->_relationsBeginned, $relations);
                }
            }
        }
        return true;
    }

    protected function getRelationsToSave($relations) {
        if (is_string($relations)) {
            if (!in_array($relations, array_keys($this->_relationAliasToClassName))) {
                throw new DbObjectException($this, "Unknown relation [{$relations}] in [{$this->_getModelAlias()}]");
            }
            $relations = array($relations);
        } else if (is_array($relations)) {
            $diff = array(); array_intersect($relations, array_keys($this->_relationAliasToClassName));
            foreach ($relations as $alias) {
                if (!array_key_exists($alias, $this->_relationAliasToClassName)) {
                    $diff[] = $alias;
                }
            }
            if (!empty($diff)) {
                $unknown = '[' . implode('], [', $diff) . ']';
                throw new DbObjectException($this, "Unknown relations $unknown in [{$this->_getModelAlias()}]");
            }
        }
        $relatedObjects = array();
        foreach ($relations as $alias) {
            if (empty($this->_relatedObjects[$alias])) {
                continue;
            }
            $relatedObjects[$alias] = $this->_relatedObjects[$alias];
            $this->_cleanRelatedObject($alias);
        }
        return $relatedObjects;
    }

    /**
     * Save updated field name to $this->updatedFields
     * If commit() called - $fieldName will be appended to $this->updatedFields
     * If commit() was not called - $fieldName will replace $this->updatedFields by array($fieldName)
     * @param string $fieldName
     * @return $this
     */
    public function fieldUpdated($fieldName) {
        if ($this->_allowFieldsUpdatesTracking) {
            if ($this->_isStoringFieldUpdates) {
                $this->_updatedFields[] = $fieldName;
            } else {
                $this->_updatedFields = array($fieldName); //< for single field saving
            }
        }
        // reinit related objects
        foreach ($this->_getField($fieldName)->getRelations() as $alias => $relationConfig) {
            if (!empty($this->_relatedObjects[$alias])) {
                $this->_initRelatedObject($alias);
            }
        }
        return $this;
    }

    /**
     * Read data from DB using Model
     * @param int|string $pkValue - primary key value
     * @param array|string $fieldNames - list of fields to get
     * @param array|string|null|bool $relations - related objects to read
     * @return $this
     */
    public function read($pkValue, $fieldNames = '*', $relations = false) {
        $this->_setFieldValue($this->_getPkField(), $pkValue);
        return $this->find($this->getFindByPkConditions(), $fieldNames, $relations);
    }

    /**
     * Reload data from DB using stored pk value
     * @param array|string $fieldNames - list of fields to get
     * @param array|string|null|bool $relations - related objects to read. null: reload currently loaded related objects
     * @return $this
     * @throws DbObjectException
     */
    public function reload($fieldNames = '*', $relations = null) {
        if (!$this->_isFieldHasEmptyValue($this->_getPkFieldName())) {
            if ($relations === null) {
                $relations = array();
                foreach ($this->_relatedObjects as $alias => $object) {
                    if (!empty($object)) {
                        if (
                            is_array($object)
                            || (
                                $object instanceof DbObject
                                && $object->exists()
                            )
                        ) {
                            $relations[] = $alias;
                        }
                    }
                }
            }
            return $this->find($this->getFindByPkConditions(), $fieldNames, $relations);
        } else {
            throw new DbObjectException($this, 'Cannot load object if primary key is empty');
        }
    }

    /**
     * Read required fields from DB and update current object
     * Note: does not work with not existing object
     * @param string|array $fieldNames
     * @return $this
     */
    public function readFields($fieldNames = '*') {
        if ($this->exists()) {
            return $this->find($this->getFindByPkConditions(), $fieldNames);
        } else {
            return $this;
        }
    }

    /**
     * Read related objects
     * @param null|array|string|false $relations - what relations to read
     *      false: no relations (just clean)
     *      null|empty|true: all relations
     *      string|array: relation or list of relations
     */
    public function readRelations($relations = null) {
        $this->_cleanRelatedObjects();
        if ($relations !== false) {
            if (empty($relations) || $relations === true) {
                $relations = array_keys($this->_getTableConfig()->getRelations());
            } else if (is_string($relations)) {
                $relations = array($relations);
            }
            foreach ($relations as $relationAlias) {
                $this->_findRelatedObject($relationAlias);
            }
        }
    }

    /**
     * validate related object(s) data
     * @param string $relationAlias
     * @param bool $ignorePkNotSetError - true: exception '[local_field] is empty' will be ignored when local_field is primary key
     * @return bool|null
     *      - true: (all) related object(s) related to this one
     *      - false: (any) related object(s) does not related to this one
     *      - null: related object not loaded
     * @throws DbObjectException when local field is empty
     */
    public function validateRelationData($relationAlias, $ignorePkNotSetError = false) {
        if ($this->_relatedObjects[$relationAlias] === false) {
            // not loaded
            return null;
        } else if (empty($this->_relatedObjects[$relationAlias])) {
            // not exists
            return false;
        }
        // validate if  related object belongs to this object
        $localField = $this->_getLocalFieldNameForRelation($relationAlias);
        if ($this->_isFieldHasEmptyValue($localField)) {
            // local field empty - bad situation but possible when creating new record together with all related
            if (!$ignorePkNotSetError || ($localField != $this->_getPkFieldName() && $this->_getField($localField)->isRequiredOnAnyAction())) {
                throw new DbObjectException($this, "Cannot validate relation between [{$this->_getModelAlias()}] and [{$relationAlias}]: [{$this->_getModelAlias()}->{$localField}] is empty");
            }
        } else {
            $foreignField = $this->_getForeignFieldNameForRelation($relationAlias);
            if ($this->_getTypeOfRealation($relationAlias) === DbRelationConfig::HAS_MANY) {
                $objects = $this->_relatedObjects[$relationAlias];
            } else {
                $objects = array($this->_relatedObjects[$relationAlias]);
            }
            // if any related object is invalid - whole set is invalid
            /** @var DbObject $relatedObject */
            foreach ($objects as $relatedObject) {
                if (
                    $relatedObject->_isFieldHasEmptyValue($foreignField)
                    || $relatedObject->_getFieldValue($foreignField) !== $this->_getFieldValue($localField)
                ) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Find related object or list of objects by relation alias
     * @param string $relationAlias
     * @return $this|$this[]|bool
     * @throws DbObjectException when local field is empty
     */
    protected function _findRelatedObject($relationAlias) {
        $this->_relatedObjects[$relationAlias] = array();
        $localFieldName = $this->_getLocalFieldNameForRelation($relationAlias);
        if ($this->_isFieldHasEmptyValue($localFieldName)) {
            if (!$this->_getField($localFieldName)->canBeNull()) {
                // local field empty - bad situation
                throw new DbObjectException($this, "Cannot find related object [{$relationAlias}] [{$this->_getModelAlias()}->{$localFieldName}] is empty");
            } else {
                return null;
            }
        } else {
            // load object[s]
            $conditions = $this->_insertDataIntoRelationConditions($this->_aliasToJoinConditions[$relationAlias]);
            if ($this->_getTypeOfRealation($relationAlias) === DbRelationConfig::HAS_MANY) {
                $model = $this->_getModel()->getRelatedModel($relationAlias);
                // change model alias for some time
                $modelAliasBak = $model->getAlias();
                $model->setAlias($relationAlias);
                $this->_relatedObjects[$relationAlias] = $model->select('*', $conditions, true);
                $model->setAlias($modelAliasBak); //< restore model alias to default value
            } else {
                /** @var DbObject $relatedObject */
                $relatedObject = $this->_initRelatedObject($relationAlias);
                // change model alias for some time
                $modelAliasBak = $relatedObject->_getModel()->getAlias();
                $relatedObject->_getModel()->setAlias($relationAlias);
                $relatedObject->find($conditions)->linkTo($this);
                $relatedObject->_getModel()->setAlias($modelAliasBak); //< restore model alias to default value
            }
            return $this->_relatedObjects[$relationAlias];
        }
    }

    /**
     * Links current object with passed on
     * @param DbObject $dbObject
     * @return $this
     * @throws DbObjectException
     */
    public function linkTo(DbObject $dbObject) {
        $relationAlias = $dbObject->_getModel()->getAlias();
        if ($this->_getTypeOfRealation($relationAlias) === DbRelationConfig::HAS_MANY) {
            throw new DbObjectException($this, "Cannot attach [single related object] to [has many] relation [{$relationAlias}]");
        }
        $this->_initRelatedObject($relationAlias, $dbObject);
        return $this;
    }

    /**
     * Read data from DB using Model
     * @param string|array $conditions - conditions to use
     * @param array|string $fieldNames - list of fields to get
     * @param array|null|string $relations - list of relations to read with object
     * @return $this
     */
    public function find($conditions, $fieldNames = '*', $relations = array()) {
        if (is_array($fieldNames) && !in_array($this->_getPkFieldName(), $fieldNames)) {
            $fieldNames[] = $this->_getPkFieldName();
        } else if (!is_array($fieldNames) && $fieldNames !== '*') {
            $fieldNames = array($this->_getPkFieldName(), $fieldNames);
        }
        $data = $this->_getModel()->getOne($fieldNames, $conditions, false, false);
        if (!empty($data)) {
            $this->fromDbData($data, false);
            if (!empty($relations) && (is_array($relations) || is_string($relations))) {
                $this->readRelations($relations);
            }
        } else {
            $this->reset();
        }
        return $this;
    }

    /**
     * Mark values of all fields with isset values as db values
     * Used after saving data
     */
    protected function markAllSetFieldsAsDbFields() {
        foreach ($this->_fields as $dbField) {
            if ($dbField->hasValue()) {
                $dbField->setValueReceivedFromDb(true);
            }
        }
    }

    /**
     * Save object to db
     * @param bool $verifyDbExistance - true: verifies if primary key exists in db before doing save operation
     * @param bool $createIfNotExists - true: used when $verifyDbExistance == true but record not exists in DB
     * @param bool|array|string $saveRelations
     *      - false: do not save related objects
     *      - true: all relations
     *      - array and string - related objects aliases to save
     * @return bool
     * @throws DbObjectException
     */
    public function save($verifyDbExistance = false, $createIfNotExists = false, $saveRelations = false) {
        $errors = $this->validate(null, true);
        if (!empty($errors)) {
            return false;
        }
        $localTransaction = false;
        $model = $this->_getModel();
        if (!$model->inTransaction()) {
            $model->begin();
            $localTransaction = true;
        }
        $exists = $this->exists($verifyDbExistance);
        if ($verifyDbExistance && !$exists && !$createIfNotExists) {
            $this->_customErrors[$this->_getPkFieldName()] = '@!db.error_edit_not_existing_record@';
            if ($localTransaction) {
                $model->rollback();
            }
            return false;
        }
        $relatedObjects = !empty($saveRelations)
            ? $this->getRelationsToSave($saveRelations)
            : array();
        if (!$exists) {
            $this->_allowFieldsUpdatesTracking = false;
            $ret = $model->insert($this->toStrictArray(), '*');
            if (!empty($ret)) {
                $this->_updateWithDbValues($ret);
            }
            $this->_allowFieldsUpdatesTracking = true;
        } else {
            $dataToSave = $this->toStrictArray(null, true);
            unset($dataToSave[$this->_getPkFieldName()]);
            if (!empty($dataToSave)) {
                $this->_allowFieldsUpdatesTracking = false;
                $ret = $model->update($dataToSave, $this->getFindByPkConditions(), '*');
                if (!empty($ret) && count($ret) == 1) {
                    $ret = $ret[0];
                    $this->_updateWithDbValues($ret);
                } else if (count($ret) > 1) {
                    $model->rollback();
                    throw new DbObjectException($this, 'Attempt to update [' . count($ret) . '] records instead of 1: ' . $model->lastQuery());
                }
                $this->_allowFieldsUpdatesTracking = true;
            } else {
                // nothing to update
                $ret = true;
            }
        }
        if (!empty($ret)) {
            // save attached files
            if ($this->_hasFileFields()) {
                $this->saveFiles();
            }
            if (is_array($ret)) {
                // set $ret as db data (it is all fields actually)
                $this->fromDbData($ret);
            } else {
                // mark updated data as db data
                $this->markAllSetFieldsAsDbFields();
            }
            if (!empty($saveRelations)) {
                foreach ($relatedObjects as $alias => $dbObject) {
                    $this->linkTo($dbObject);
                }
                $ret = $this->runActionForRelations($saveRelations, 'save');
            }
        }
        if ($localTransaction) {
            if ($ret) {
                $model->commit();
            } else {
                $model->rollback();
            }
        }
        if (!empty($ret)) {
            $this->afterSave(!$exists);
        }
        return !empty($ret);
    }

    protected function afterSave($created) {

    }

    /**
     * Save attached files
     * @param null|string|array $fieldNames
     * @return bool
     */
    protected function saveFiles($fieldNames = null) {
        if (!$this->exists()) {
            return false;
        }
        if (empty($fieldNames) || !is_array($fieldNames)) {
            $fieldNames = $this->_getFileFields();
        } else {
            $fieldNames = array_intersect(array_keys($this->_getFileFields()), $fieldNames);
        }
        foreach ($fieldNames as $fieldName) {
            /** @var FileField $field */
            $field = $this->_getFileField($fieldName);
            if ($field->isUploadedFile()) {
                $this->saveFile($fieldName, $field->getValue());
            }
        }
        return true;
    }

    /**
     * Save single field value to db
     * @param string $fieldName
     * @param mixed $value
     * @return bool
     * @throws DbObjectException when field does not belong to db object
     */
    public function saveField($fieldName, $value) {
        return $this->begin()->_setFieldValue($fieldName, $value)->commit();
    }

    /**
     * Save updates for certain fields of existing objects
     * Note: does not work with relations
     * @params format: saveUpdates(array) | saveUpdates(field1 [,field2])
     * @param array|string|null $fieldNames
     * @param string $fieldName2
     * @param string $fieldName3
     * @return bool
     */
    public function saveUpdates($fieldNames = null, $fieldName2 = null, $fieldName3 = null) {
        if ($this->exists()) {
            $localTransaction = false;
            $fieldNames = func_get_args();
            if (empty($fieldNames)) {
                $fieldNames = null;
            } else if (is_array($fieldNames[0])) {
                $fieldNames = $fieldNames[0];
            }
            $errors = $this->validate($fieldNames, true);
            if (!empty($errors)) {
                return false;
            }
            $model = $this->_getModel();
            if (!$model->inTransaction()) {
                $model->begin();
                $localTransaction = true;
            }
            $dataToSave = $this->toStrictArray($fieldNames, true);
            unset($dataToSave[$this->_getPkFieldName()]);
            if (!empty($dataToSave)) {
                $ret = $model->update($dataToSave, $this->getFindByPkConditions());
            } else {
                $ret = true;
            }
            if (!empty($ret) && $this->_hasFileFields()) {
                // save attached files
                $this->saveFiles();
                $ret = true;
            }
            if (!empty($ret)) {
                if ($localTransaction) {
                    $model->commit();
                }
                $this->markAllSetFieldsAsDbFields();
            } else {
                if ($localTransaction) {
                    $model->rollback();
                }
            }
            return !empty($ret);
        }
        return false;
    }

    /**
     * Delete current object
     * @param bool $resetFields - true: will reset DbFields (default) | false: only primary key will be reset
     * @param bool $ignoreIfNotExists - true: will not throw exception if object not exists
     * @return $this
     * @throws DbObjectException when object not exists
     */
    public function delete($resetFields = true, $ignoreIfNotExists = false) {
        if (!$this->exists()) {
            if (!$ignoreIfNotExists) {
                throw new DbObjectException($this, 'Trying to delete [' . get_class($this) . '] object without primary key');
            }
        } else {
            $model = $this->_getModel();
            $alreadyInTransaction = $model->inTransaction();
            if (!$alreadyInTransaction) {
                $model->begin();
            }
            $model->delete($this->getFindByPkConditions());
            $this->afterDelete();
            if (!$alreadyInTransaction) {
                $model->commit();
            }
            if (!$this->_dontDeleteFiles && $this->_hasFileFields()) {
                $this->deleteFilesForAllFields();
            }
        }
        // note: related objects delete must be managed only by database relations (foreign keys), not here
        if ($resetFields) {
            $this->reset();
        } else {
            $this->_getPkField()->resetValue();
        }
        return $this;
    }

    /**
     * Delete all files attached to DbObject fields
     */
    public function deleteFilesForAllFields() {
        if (!$this->exists()) {
            return false;
        }
        foreach ($this->_getFileFields() as $fieldName => $tableColumnConfig) {
            Folder::load($this->buildPathToFiles($fieldName))->delete();
        }
        return true;
    }

    /**
     * Called after successful delete but before field values resetted
     * (for child classes)
     */
    protected function afterDelete() {

    }

    /**
     * Check if db record exists
     * @param bool $fromDb = true: send query to db | false: check if primary key is not empty
     * @return bool
     */
    public function exists($fromDb = false) {
        $pkSet = $this->_getPkField()->hasNotEmptyValue();
        if ($fromDb) {
            return $pkSet && $this->_getModel()->exists($this->getFindByPkConditions());
        } else {
            return $pkSet;
        }
    }

    /**
     * collect conditions for search by primary key query
     * @return array
     */
    protected function getFindByPkConditions() {
        return array($this->_getPkFieldName() => $this->_getPkValue());
    }

    /**
     * Collect all values into associative array (all field values are validated)
     * Used to collect values to write them to db
     * @param array|null $fieldNames - will return only this fields (if not skipped).
     *      Note: pk field added automatically if object has it
     * @param bool $onlyUpdatedFields - true: will return only field values that were updated (field->isDbValue == false)
     * @return array
     * @throws DbObjectException when some field value validation fails
     */
    public function toStrictArray($fieldNames = null, $onlyUpdatedFields = false) {
        $values = array();
        if (empty($fieldNames) || !is_array($fieldNames)) {
            $fieldNames = $this->_allFieldNames;
        }
        if ($this->exists() && !in_array($this->_getPkFieldName(), $fieldNames)) {
            $fieldNames[] = $this->_getPkFieldName();
        }
        foreach ($fieldNames as $fieldName) {
            $field = $this->_getField($fieldName);
            if (!$field->canBeSkipped() && (!$onlyUpdatedFields || !$field->isValueReceivedFromDb())) {
                $values[$fieldName] = $field->getValue();
            }
        }
        return $values;
    }

    /**
     * Collect available values into associative array (does not validate field values)
     * Used to just get values from object. Also can be overwritten in child classes to add/remove specific values
     * @param array|null $fieldNames - will return only this fields (if not skipped)
     *      Note: pk field added automatically if object has it
     * @param array|string|bool $relations - array and string: relations to return | true: all relations | false: without relations
     * @param bool $forceRelationsRead - true: relations will be read before processing | false: only previously read relations will be returned
     * @return array
     */
    public function toPublicArray($fieldNames = null, $relations = false, $forceRelationsRead = true) {
        $values = array();
        if (empty($fieldNames) || !is_array($fieldNames)) {
            $fieldNames = $this->_allFieldNames;
        }
        if ($this->exists() && !in_array($this->_getPkFieldName(), $fieldNames)) {
            $fieldNames[] = $this->_getPkFieldName();
        }
        foreach ($fieldNames as $fieldName) {
            $field = $this->_getField($fieldName);
            if ($field->hasValue()) {
                $values[$fieldName] = $field->getValue();
            } else if ($this->exists() && $field->isFile()) {
                $field->setValue($this->_getPkValue()); //< this will trigger file path generation
                $values[$fieldName] = $field->getValue();
                /*$server = !empty($this->model->fields[$name]['server']) ? $this->model->fields[$name]['server'] : null;
                if ($this->_fields[$name]->isImage && $server == \Server::alias()) {
                    $values[$name . '_path'] = $this->{$name . '_path'};
                }*/
                if ($field->isImage()) {
                    /** @var ImageField $field  */
                    $values[$fieldName . '_path'] = $field->getFilePath();
                }
            }
        }

        return $values + $this->relationsToPublicArray($relations, $forceRelationsRead);
    }

    /**
     * @param null|string|array $relations
     * @param bool $forceRelationsRead
     * @return array
     * @throws DbObjectException
     */
    public function relationsToPublicArray($relations = null, $forceRelationsRead = true) {
        if (empty($relations)) {
            return array();
        } if ($relations === true) {
            $relations = array_keys($this->_getTableConfig()->getRelations());
        } else if (is_string($relations)) {
            $relations = array($relations);
        } else if (!is_array($relations)) {
            throw new DbObjectException($this, 'DbObject->relationsToPublicArray: $relations arg should contain string, bool or array)');
        }
        $return = array();
        foreach ($relations as $relationAlias => $fieldNames) {
            if (is_numeric($relationAlias)) {
                $relationAlias = $fieldNames;
                $fieldNames = null;
            }
            if (!$this->_hasRelation($relationAlias)) {
                throw new DbObjectException($this, "Unknown relation with alias [$relationAlias]");
            }
            $return[$relationAlias] = array();
            if ($forceRelationsRead) {
                $this->_findRelatedObject($relationAlias); //< read relation data
            }
            // show related object if it is set or
            if (!empty($this->_relatedObjects[$relationAlias])) {
                if (is_array($this->_relatedObjects[$relationAlias])) {
                    /** @var DbObject $object */
                    foreach ($this->_relatedObjects[$relationAlias] as $object) {
                        $return[$relationAlias][] = $object->toPublicArray($fieldNames);
                    }
                } else {
                    $return[$relationAlias] = $this->_relatedObjects[$relationAlias]->toPublicArray($fieldNames);
                }
            }
        }
        return $return;
    }

    /**
     * Collect default values for the fields
     * @param array|null $fieldNames - will return only this fields (if not skipped)
     * @return array
     * @throws DbFieldException
     * @throws DbObjectException
     */
    public function getDefaultsArray($fieldNames = null) {
        $values = array();
        if (is_string($fieldNames)) {
            $fieldNames = array($fieldNames);
        } else if (empty($fieldNames)) {
            $fieldNames = $this->_allFieldNames;
        } else if (!is_array($fieldNames)) {
            throw new DbObjectException($this, "getDefaultsArray: \$fieldNames argument must be empty, string or array");
        }
        foreach ($fieldNames as $name) {
            $values[$name] = $this->_getField($name)->getDefaultValue();
        }
        return $values;
    }

    /**
     * Collect values of all fields avoiding exceptions
     * Fields that were not set will be ignored
     * @return array
     */
    public function getFieldsValues() {
        $values = array();
        foreach ($this->_fields as $name => $dbField) {
            if ($dbField->hasValue()) {
                $values[$name] = $dbField->getValue();
            }
        }
        return $values;
    }

    /**
     * Get subdir to files based on primary key and maybe some other custom things
     * @param string $ds - directory separator
     * @return string
     */
    public function getFilesSubdir($ds) {
        return $this->_getPkValue();
    }

    /**
     * Get server url where files are stored (ex: http://sub.server.com)
     * @param string $field
     * @return string
     */
    /*public function getFilesServer($field) {
        return (!empty($this->model->fields[$field]['server'])) ? \Server::base_url($this->model->fields[$field]['server']) : '';
    }*/

    /**
     * Get relative url to files by $field
     * @param string $fieldName
     * @return string
     */
    public function getFilesBaseRelativeUrl($fieldName) {
        $objectSubdir = $this->getFilesSubdir('/');
        if (!empty($objectSubdir)) {
            $objectSubdir = '/' . trim($objectSubdir, '/\\') . '/';
        }
        $fileField = $this->_getFileField($fieldName);
        return '/' . trim($fileField->getFilesBaseUrl(), '/\\') . $objectSubdir . $fileField->getFilesSubdir();
    }

    /**
     * Get absolute url to files by $field
     * @param $fieldName
     * @return string
     */
    public function getFilesAbsoluteUrl($fieldName) {
        return /*rtrim($this->getFilesServer($field), '/') . */$this->getFilesBaseRelativeUrl($fieldName);
    }

    /**
     * Get base file name for $field (without suffix and extension)
     * @param string $fieldName
     * @return string
     */
    public function getBaseFileName($fieldName) {
        return $this->_getFileField($fieldName)->getFileName($fieldName);
    }

    /**
     * Get full file name for $field (with suffix and extension)
     * @param string $fieldName
     * @param string $suffix
     * @return string
     */
    public function getFullFileName($fieldName, $suffix = '') {
        $baseName = $this->getBaseFileName($fieldName) . $suffix;
        $pathTofiles = $this->buildPathToFiles($fieldName);
        $ext = $this->_getFileField($fieldName)->getDefaultFileExtension();
        if ($ext !== null) {
            $baseName = '.' . $ext;
        } else if (File::exist($pathTofiles . $baseName . '.ext')) {
            $baseName .= File::contents();
        }
        return $baseName;
    }

    /**
     * Build FS path to files (absolute FS path to folder with files)
     * @param string $fieldName
     * @return string
     */
    public function buildPathToFiles($fieldName) {
        if (!empty($fieldName) && $this->exists() && $this->_getFileField($fieldName)) {
            $objectSubdir = $this->getFilesSubdir(DIRECTORY_SEPARATOR);
            if (!empty($objectSubdir)) {
                $objectSubdir = DIRECTORY_SEPARATOR . trim($objectSubdir, '/\\') . DIRECTORY_SEPARATOR;
            }
            $fileField = $this->_getFileField($fieldName);
            return rtrim($fileField->getFilesBasePath(), '/\\') . $objectSubdir . $fileField->getFilesSubdir();
        }
        return 'undefined.file';
    }

    /**
     * Build base url to files (url to folder with files)
     * @param string $fieldName
     * @return string
     */
    public function buildBaseUrlToFiles($fieldName) {
        if (!empty($fieldName) && $this->exists() && $this->_getFileField($fieldName)) {
            return $this->getFilesAbsoluteUrl($fieldName);
        }
        return 'undefined.file';
    }

    /**
     * Get urls to images
     * @param string $fieldName
     * @return array
     */
    public function getImagesUrl($fieldName) {
        $images = array();
        if (!empty($fieldName) && $this->exists() && $this->_getFileField($fieldName)) {
            $images = ImageUtils::getVersionsUrls(
                $this->buildPathToFiles($fieldName),
                $this->buildBaseUrlToFiles($fieldName),
                $this->getBaseFileName($fieldName),
                $this->_getFileField($fieldName)->getImageVersions()
            );
        }
        return $images;
    }

    /**
     * Get fs paths to images
     * @param string $fieldName
     * @return array
     */
    public function getImagesPaths($fieldName) {
        $images = array();
        if (!empty($fieldName) && $this->exists() && $this->_getFileField($fieldName)) {
            $images = ImageUtils::getVersionsPaths(
                $this->buildPathToFiles($fieldName),
                $this->getBaseFileName($fieldName),
                $this->_getFileField($fieldName)->getImageVersions()
            );
        }
        return $images;
    }

    /**
     * Get fs path to file
     * @param string $fieldName
     * @return string
     */
    public function getFilePath($fieldName) {
        return $this->buildPathToFiles($fieldName) . $this->getFullFileName($fieldName);
    }

    /**
     * Get url to file
     * @param string $fieldName
     * @return string
     */
    public function getFileUrl($fieldName) {
        $ret = $this->buildBaseUrlToFiles($fieldName) . $this->getFullFileName($fieldName);
        return $ret;
    }

    protected function canSaveFile($fieldName, $fileInfo) {
        return !empty($fileInfo)
            && $this->exists(true)
            && $this->_getFileField($fieldName)
            && Utils::isUploadedFile($fileInfo);
    }

    /**
     * Save file for field using field settings ($this->fields[$field])
     * If field type is image - will create required image resizes
     * @param string $fieldName
     * @param array $fileInfo - uploaded file info
     * @return bool|string - string: path to uploaded file (not image)
     */
    public function saveFile($fieldName, $fileInfo) {
        if ($this->canSaveFile($fieldName, $fileInfo)) {
            if (!defined('UNLIMITED_EXECUTION') || !UNLIMITED_EXECUTION) {
                set_time_limit(90);
                ini_set('memory_limit', '128M');
            }
            $fileField = $this->_getFileField($fieldName);
            $baseFileName = $this->getBaseFileName($fieldName);
            if ($fileField->isImage()) {
                $pathToFiles = $this->buildPathToFiles($fieldName);
                // save image and create requred versions for it
                return ImageUtils::resize($fileInfo, $pathToFiles, $baseFileName, $fileField->getImageVersions());
            } else {
                // save file
               return $this->saveFileWithCustomName($fieldName, $fileInfo);
            }
        }
        return false;
    }

    /**
     * Save file for field using field settings ($this->fields[$field]) and provided file suffix
     * Note: will not create image resizes
     * @param string $fieldName
     * @param array $fileInfo - uploaded file info
     * @param string $fileSuffix - custom file name
     * @return bool|string - string: path to uploaded file
     */
    public function saveFileWithCustomName($fieldName, $fileInfo, $fileSuffix = '') {
        if ($this->canSaveFile($fieldName, $fileInfo)) {
            $pathToFiles = $this->buildPathToFiles($fieldName);
            if (!is_dir($pathToFiles)) {
                Folder::add($pathToFiles, 0777);
            }
            $filePath = $pathToFiles . $this->getBaseFileName($fieldName) . $fileSuffix;
            $ext = $this->detectUploadedFileExtension($fileInfo, $this->_getFileField($fieldName));
            if ($ext === false) {
                return false;
            } else if (!empty($ext)) {
                $filePath .= '.' . $ext;
            }
            // if file $fileInfo['tmp_name'] differs from target file path
            if ($fileInfo['tmp_name'] != $filePath) {
                // move tmp file to target file path
                if (!File::load($fileInfo['tmp_name'])->move($filePath, 0666)) {
                    return false;
                }
            }
            return $filePath;
        }
        return false;
    }

    /**
     * Detect Uploaded file extension by file name or content type
     * @param array $fileInfo - uploaded file info
     * @param FileField $fileField - file field info (may contain 'extension' key to limit possible extensions)
     * @param string|bool $saveExtToFile - string: file path to save extension to.
     *      Extension saved to file only when empty($fieldInfo['extension']) and extesion detected
     * @return bool|string -
     *      string: file extension without leading point (ex: 'mp4', 'mov', '')
     * false: invalid file info or not supported extension
     * @throws DbFieldException
     */
    protected function detectUploadedFileExtension($fileInfo, FileField $fileField, $saveExtToFile = false) {
        if (empty($fileInfo['type']) && empty($fileInfo['name'])) {
            return false;
        }
        // test content type
        $receivedExt = false;
        if (!empty($fileInfo['type'])) {
            $receivedExt = array_search($fileInfo['type'], self::$extToConetntType);
        }
        if (!empty($fileInfo['name']) && (empty($receivedExt) || is_numeric($receivedExt))) {
            $receivedExt = preg_match('%\.([a-zA-Z0-9]+)\s*$%is', $fileInfo['name'], $matches) ? $matches[1] : '';
        }
        $expectedExts = $fileField->getAllwedFileExtensions();
        if (!empty($expectedExts)) {
            if (empty($receivedExt)) {
                $receivedExt = array_shift($expectedExts);
            } else if (!in_array($receivedExt, $expectedExts)) {
                throw new DbFieldException(
                    $fileField,
                    "Uploaded file has extension [$receivedExt] that is not allowed",
                    DbExceptionCode::FILE_EXTENSION_NOT_ALLOWED
                );
            }
        } else if ($saveExtToFile && !empty($receivedExt)) {
            File::save($saveExtToFile, $receivedExt, 0666);
        }
        return $receivedExt;
    }

    /**
     * Delete files attached to DbObject field
     * @param string $fieldName
     * @param string $fileSuffix
     */
    public function deleteFilesForField($fieldName, $fileSuffix = '') {
        if (
            $this->_getFileField($fieldName)
            && $this->exists(true)
        ) {
            $pathToFiles = $this->buildPathToFiles($fieldName);
            if (is_dir($pathToFiles)) {
                $files = scandir($pathToFiles);
                $baseFileName = $this->getBaseFileName($fieldName);
                foreach ($files as $fileName) {
                    if (preg_match("%^{$baseFileName}{$fileSuffix}%is", $fileName)) {
                        @File::remove(rtrim($pathToFiles, '/\\') . DIRECTORY_SEPARATOR . $fileName);
                    }
                }
            }
        }
    }

}