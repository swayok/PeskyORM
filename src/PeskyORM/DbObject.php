<?php

namespace PeskyORM;
use PeskyORM\DbColumnConfig;
use PeskyORM\DbObjectField\DateField;
use PeskyORM\DbObjectField\FileField;
use PeskyORM\DbObjectField\ImageField;
use PeskyORM\DbObjectField\JsonField;
use PeskyORM\DbObjectField\PasswordField;
use PeskyORM\DbObjectField\TimestampField;
use PeskyORM\Exception\DbObjectFieldException;
use PeskyORM\Exception\DbObjectException;
use PeskyORM\Exception\DbObjectValidationException;
use Swayok\Utils\Folder;
use Swayok\Utils\StringUtils;

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
     * associative list that maps related object alias to relation select conditions with data inserts
     * @var array
     */
    protected $_aliasToSelectConditions = array();

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
    /**
     * @var string
     */
    protected $_baseModelClass = DbModel::class;

    /**
     * $this->toPublicArray() will automatically add PK value to returned array
     * @var bool
     */
    protected $_autoAddPkValueToPublicArray = true;

    const ERROR_OBJECT_NOT_EXISTS = 'Object not exists or not loaded';

    /**
     * @param DbModel|null $model
     * @param null|array|string|int $dataOrPkValue - null: do nothing | int and string: is primary key (read db) | array: object data
     * @param bool $ignoreUnknownData - used only when $data not empty and is array
     *      true: filters $data that does not belong t0o this object
     *      false: $data that does not belong to this object will trigger exceptions
     * @param bool $isDbValues - true: indicates that field values passsed via $data as array are db values
     * @throws DbObjectException
     * @return $this
     */
    static public function create($dataOrPkValue = null, $ignoreUnknownData = false, $isDbValues = false, $model = null) {
        $className = get_called_class();
        return new $className($dataOrPkValue, $ignoreUnknownData, $isDbValues);
    }

    /**
     * Read data from DB using Model
     * @param string|array $conditions - conditions to use
     * @param array|string $fieldNames - list of fields to get
     * @param array|null|string $relations - list of relations to read with object
     * @return $this
     * @throws \PeskyORM\Exception\DbObjectException
     */
    static public function search($conditions, $fieldNames = '*', $relations = array()) {
        return self::create()->find($conditions, $fieldNames, $relations);
    }

    /**
     * @param DbModel|null $model
     * @param null|array|string|int $dataOrPkValue - null: do nothing | int and string: is primary key (read db) | array: object data
     * @param bool $ignoreUnknownData - used only when $data not empty and is array
     *      true: filters $data that does not belong t0o this object
     *      false: $data that does not belong to this object will trigger exceptions
     * @param bool $isDbValues - true: indicates that field values passsed via $data as array are db values
     * @throws DbObjectException
     * @throws \PeskyORM\Exception\DbModelException
     */
    public function __construct($dataOrPkValue = null, $ignoreUnknownData = false, $isDbValues = false, $model = null) {
        if (!empty($model)) {
            if (!is_object($model)) {
                throw new DbObjectException($this, 'Model should be an object of class inherited from ' . DbModel::class . ' class');
            } else if (!$model instanceof DbModel) {
                throw new DbObjectException($this, 'Model ' . get_class($model) . ' should be inherited from ' . DbModel::class . ' class');
            }
            $this->_model = $model;
        } else {
            $this->_model = $this->_loadModel();
        }
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
            list($this->_aliasToJoinConditions[$alias], $this->_aliasToSelectConditions[$alias]) = $this->_buildRelationConditions($alias, $settings);
            $this->_relationAliasToClassName[$alias] = $this->_model->getFullDbObjectClass($settings->getForeignTable());
        }
        $this->_cleanRelatedObjects();
        // set values if possible
        if (!empty($dataOrPkValue)) {
            if (is_array($dataOrPkValue)) {
                $this->_fromData($dataOrPkValue, !empty($ignoreUnknownData), $isDbValues);
            } else {
                $this->read($dataOrPkValue);
            }
        }
    }

    /**
     * @return \PeskyORM\DbTableConfig
     * @throws \PeskyORM\Exception\DbModelException
     */
    public function _getTableConfig() {
        return $this->_getModel()->getTableConfig();
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    public function _hasField($fieldName) {
        return is_string($fieldName) && (!empty($this->_fields[$fieldName]) || $this->isSuffixedField($fieldName));
    }

    /**
     * Check if field name has a suffix like '_ts', '_array', etc and it really exists
     * @param string $fieldName
     * @return bool
     */
    protected function isSuffixedField($fieldName) {
        if (array_key_exists($fieldName, $this->_fields)) {
            return false;
        } else {
            $fieldName = $this->getSuffixedFieldName($fieldName);
            return !empty($fieldName) && !empty($this->_fields[$fieldName]);
        }
    }

    /**
     * @param string $fieldName
     * @return DbObjectField
     * @throws DbObjectException
     */
    protected function getSuffixedField($fieldName) {
        $realFieldName = $this->getSuffixedFieldName($fieldName);
        if (!empty($realFieldName) && !empty($this->_fields[$realFieldName])) {
            return $this->_fields[$realFieldName];
        }
        throw new DbObjectException($this, "Db object has no field called [{$fieldName}]");
    }

    /**
     * @param string $fieldName
     * @return bool
     */
    protected function getSuffixedFieldName($fieldName) {
        if (preg_match('%^(.+)_[a-zA-Z0-9]+$%i', $fieldName, $matches)) {
            return $matches[1];
        }
        return false;
    }

    /**
     * @param $fieldName
     * @return DbObjectField|FileField|ImageField|PasswordField|TimestampField
     * @throws DbObjectException
     */
    public function _getField($fieldName) {
        if (!$this->_hasField($fieldName)) {
            throw new DbObjectException($this, "Db object has no field called [{$fieldName}]");
        }
        if (empty($this->_fields[$fieldName])) {
            return $this->getSuffixedField($fieldName);
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
        return $this->_getModel()->getPkColumnName();
    }

    /**
     * @return mixed
     * @throws \PeskyORM\Exception\DbObjectFieldException
     * @throws \PeskyORM\Exception\DbObjectException
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
    public function _getFileFieldsConfigs() {
        return $this->_getTableConfig()->getFileColumns();
    }

    /**
     * @param string $fieldName
     * @param mixed $value
     * @return bool
     * @throws DbObjectException
     */
    public function isValidFieldValue($fieldName, $value) {
        return $this->_getField($fieldName)->isValidValueFormat($value);
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

    protected function _loadModel() {
        $baseModelClass = $this->_baseModelClass;
        return $baseModelClass::getModelByObjectClass(get_called_class());
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
     * @throws \PeskyORM\Exception\DbTableConfigException
     */
    protected function _getLocalFieldNameForRelation($alias) {
        return $this->_getRelationConfig($alias)->getColumn();
    }

    /**
     * @param string $alias
     * @return string
     * @throws \PeskyORM\Exception\DbTableConfigException
     */
    protected function _getForeignFieldNameForRelation($alias) {
        return $this->_getRelationConfig($alias)->getForeignColumn();
    }

    /**
     * @param string $alias
     * @return string
     * @throws \PeskyORM\Exception\DbTableConfigException
     */
    protected function _getForeignTableForRelation($alias) {
        return $this->_getRelationConfig($alias)->getForeignTable();
    }

    /**
     * @param string $alias
     * @return string
     * @throws \PeskyORM\Exception\DbTableConfigException
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
    protected function _buildRelationConditions($relationAlias, DbRelationConfig $relationSettings) {
        $joinConditions = $selectConditions = $this->_getModel()->getAdditionalConditionsForRelation($relationAlias);
        $joinConditions[$relationAlias . '.' . $relationSettings->getForeignColumn()] = $this->_getModelAlias() . '.' . $relationSettings->getColumn();
        $selectConditions[$relationAlias . '.' . $relationSettings->getForeignColumn()] = ':' . $relationSettings->getColumn();
        return array($joinConditions, $selectConditions);
    }

    /**
     * Insert values into conditions instead of "$this->alias"."field_name" and "field_name"
     * @param $conditions
     * @return array
     */
    protected function _insertDataIntoRelationConditions($relationAlias) {
        $dbObject = $this;
        $replacer = function ($matches) use ($dbObject) {
            if ($dbObject->_hasField($matches[1])) {
                return $dbObject->{$matches[1]};
            } else {
                return $matches[0];
            }
        };
        $conditions = $this->_aliasToSelectConditions[$relationAlias];
        $newConditions = array();
        $fields = array_keys($this->_fields);
        foreach ($conditions as $key => $value) {
            foreach ($fields as $name) {
                $key = preg_replace_callback("%:({$name})%is", $replacer, $key);
                $value = preg_replace_callback("%:({$name})%is", $replacer, $value);
            }
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
     * @throws \PeskyORM\Exception\DbModelException
     */
    protected function _cleanRelatedObjects() {
        foreach ($this->_getModel()->getTableRealtaions() as $alias => $settings) {
            unset($this->_relatedObjects[$alias]);
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
     * @param bool $isDbValues
     * @return bool|$this[]|$this - false: for hasMany relation
     * @throws \PeskyORM\Exception\DbModelException
     * @throws DbObjectException
     */
    protected function _initRelatedObject($relationAlias, $objectOrDataOrPkValue = null, $ignorePkNotSetError = false, $isDbValues = false) {
        if (!$this->_hasRelation($relationAlias)) {
            throw new DbObjectException($this, "Unknown relation with alias [$relationAlias]");
        }
        $localField = $this->_getLocalFieldNameForRelation($relationAlias);
        if (!$this->_hasField($localField)) {
            throw new DbObjectException($this, "Relation [$relationAlias] points to unknown field [{$localField}]");
        }
        // check if passed object is valid
        if ($this->_getTypeOfRealation($relationAlias) === DbRelationConfig::HAS_MANY) {
            $this->_initOneToManyRelation($relationAlias, $objectOrDataOrPkValue, $ignorePkNotSetError, $isDbValues);
        } else {
            $this->_initOneToOneRelation($relationAlias, $objectOrDataOrPkValue, $ignorePkNotSetError, $isDbValues);
        }
        return $this->_relatedObjects[$relationAlias];
    }

    /**
     * @param string $relationAlias
     * @param array|DbObject|null $objectOrDataOrPkValue
     * @param bool $ignorePkNotSetError
     * @param bool $isDbValues
     * @throws DbObjectException
     * @throws \PeskyORM\Exception\DbModelException
     * @throws \PeskyORM\Exception\DbTableConfigException
     * @throws \PeskyORM\Exception\DbUtilsException
     */
    protected function _initOneToOneRelation($relationAlias, $objectOrDataOrPkValue = null, $ignorePkNotSetError = false, $isDbValues = false) {
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
            $this->_validateRelatedObjectClass($objectOrDataOrPkValue, $relationAlias);
            $relatedObject = $objectOrDataOrPkValue;
        } else {
            $relatedObject = $this->_getModel()->getRelatedModel($relationAlias)->getOwnDbObject();
            if (empty($objectOrDataOrPkValue)) {
                $this->linkRelatedObjectToThis($relationAlias, $relatedObject);
            } else {
                if (is_array($objectOrDataOrPkValue)) {
                    $relatedObject->_fromData($objectOrDataOrPkValue, false, $isDbValues);
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

    /**
     * @param string $relationAlias
     * @param array|DbObject $objectsOrRecordsList
     * @param bool $ignorePkNotSetError
     * @param bool $isDbValues
     * @throws DbObjectException
     * @throws Exception\DbModelException
     * @throws \PeskyORM\Exception\DbTableConfigException
     * @throws \PeskyORM\Exception\DbUtilsException
     */
    protected function _initOneToManyRelation($relationAlias, $objectsOrRecordsList, $ignorePkNotSetError = false, $isDbValues = false) {
        // todo: implement DbObjectCollection that works as usual array but has some useful methods like find/sort/filter
        $localFieldName = $this->_getLocalFieldNameForRelation($relationAlias);
        $relationFieldName = $this->_getForeignFieldNameForRelation($relationAlias);
        if ($this->_isFieldHasEmptyValue($localFieldName)) {
            throw new DbObjectException($this, "Cannot link [{$this->_getModelAlias()}] with [{$relationAlias}]: [{$this->_getModelAlias()}->{$localFieldName}] is empty");
        }
        if (empty($objectsOrRecordsList)) {
            $relatedObjects = false; //< means not loaded
        } else if (!is_array($objectsOrRecordsList) || !isset($objectsOrRecordsList[0])) {
            throw new DbObjectException($this, "Related objects must be a plain array");
        } else {
            // array of related objects
            $relatedObjects = array();
            foreach ($objectsOrRecordsList as $index => $item) {
                if (empty($item)) {
                    throw new DbObjectException($this, "One of related objects (with index{$index}) is empty");
                } else if (is_object($item)) {
                    $this->_validateRelatedObjectClass($item, $relationAlias);
                    $relatedObjects[] = $item;
                } else {
                    // array of item data arrays or item ids
                    $relatedObjects[] = $this->_getModel()->getRelatedModel($relationAlias)->getOwnDbObject($item, false, $isDbValues);
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
     * @throws DbObjectFieldException
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
     * @param array|DbObject $data
     * @param bool|array $ignoreUnknownData -
     *      true: filters data that does not belong to this object
     *      false: data that does not belong to this object will trigger exceptions
     *      array: list of fields to use
     * @return $this
     * @throws DbObjectException when $ignoreUnknownData == false and unknown field detected in $data
     */
    public function fromData($data, $ignoreUnknownData = false) {
        $this->_fromData($data, $ignoreUnknownData, false);
        return $this;
    }

    /**
     * Update some field values without resetting loaded object.
     * Primary key value may be omitted in $data
     * Can work if $this->exists() == false, pk just not imported
     * @param array $values
     * @param bool|array $ignoreUnknownData -
     *      true: filters data that does not belong to this object
     *      false: data that does not belong to this object will trigger exceptions
     *      array: list of fields to use
     * @return $this
     * @throws DbObjectException when $ignoreUnknownData == false and unknown field detected in $data
     */
    public function updateValues($values, $ignoreUnknownData = false) {
        if ($this->exists()) {
            $values[$this->_getPkFieldName()] = $this->_getPkValue(); //< to avoid reset calling
        } else {
            $values = array_replace($this->toStrictArray(), $values);
        }
        $this->_fromData($values, $ignoreUnknownData, false);
        return $this;
    }

    /**
     * Update fields from $data and mark them as they are gathered from db (used in save())
     * @param array $data
     * @return $this
     * @throws \PeskyORM\Exception\DbObjectException
     */
    protected function _updateWithDbValues($data) {
        foreach ($data as $key => $value) {
            $this->_getField($key)->setValue($value, true);
        }
        return $this;
    }

    /**
     * Clean current fields and fill them using passed $data array + mark all passed field values as db values
     * @param array|DbObject $data
     * @param bool|array $ignoreUnknownData -
     *      true: filters data that does not belong to this object
     *      false: data that does not belong to this object will trigger exceptions
     *      array: list of fields to use
     * @return $this
     * @throws DbObjectException when $ignoreUnknownData == false and unknown field detected in $data
     */
    public function fromDbData($data, $ignoreUnknownData = false) {
        $this->_fromData($data, $ignoreUnknownData, true);
        return $this;
    }

    /**
     * Clean current fields and fill them using passed $data array
     * @param array $data
     * @param bool|array $ignoreUnknownData -
     *      true: filters data that does not belong to this object
     *      false: data that does not belong to this object will trigger exceptions
     *      array: list of fields to use
     * @param bool $isDbValues - true: all values are updates fro db values | false: all values are db values
     * @return $this
     * @throws DbObjectException when $ignoreUnknownData == false and unknown field detected in $data
     */
    protected function _fromData($data, $ignoreUnknownData = false, $isDbValues = false) {
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
            if (is_array($ignoreUnknownData)) {
                $data = array_intersect_key($data, array_flip($ignoreUnknownData));
                $ignoreUnknownData = false;
            }
            // set primary key first
            if (isset($data[$pkField->getName()])) {
                $pkField->setValue($data[$pkField->getName()], $isDbValues);
                unset($data[$pkField->getName()]);
            }
            foreach ($data as $fieldNameOrAlias => $value) {
                if ($this->_hasField($fieldNameOrAlias)) {
                    $this->_getField($fieldNameOrAlias)->setValue($value, $isDbValues);
                } else if (
                    $this->_hasRelation($fieldNameOrAlias)
                    && (
                        is_array($value)
                        || $this->_isValidRelatedObject($fieldNameOrAlias, $value)
                    )
                ) {
                    if ($this->_hasRelatedObject($fieldNameOrAlias) && !is_object($value)) {
                        if ($isDbValues) {
                            $this->_getRelatedObject($fieldNameOrAlias)->fromDbData($value);
                        } else {
                            $this->_getRelatedObject($fieldNameOrAlias)->updateValues($value);
                        }
                    } else {
                        $this->_initRelatedObject($fieldNameOrAlias, $value, true, $isDbValues);
                    }
                } else if (!$ignoreUnknownData && $fieldNameOrAlias[0] !== '_') {
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
     * @param string $relationAlias
     * @param mixed $possibleObject
     * @return bool
     */
    protected function _isValidRelatedObject($relationAlias, $possibleObject) {
        if (
            !is_object($possibleObject)
            || !($possibleObject instanceof DbObject)
            || !$this->_hasRelation($relationAlias)
            || $this->_getTypeOfRealation($relationAlias) === DbRelationConfig::HAS_MANY
        ) {
            return false;
        }
        return get_class($possibleObject) === $this->_relationAliasToClassName[$relationAlias];
    }

    /**
     * Clean all data fields (set default values)
     * @return $this
     */
    public function reset() {
        $this->_customErrors = array();
        $this->_setOriginalData(null);
        $this->cleanUpdatesOfFields();
        foreach ($this->_fields as $dbField) {
            $dbField->resetValue();
        }
        $this->_cleanRelatedObjects();
        return $this;
    }

    private function cleanUpdatesOfFields() {
        $this->_isStoringFieldUpdates = false;
        $this->_updatedFields = array();
    }

    /**
     * Validate passed $fields or all fields if $fields is empty
     * @param null|string|array $fieldNames - empty: all fields | string: single field | array: only this fields
     * @param bool $forSave - true: allows some specific validations lise isUnique
     * @return array - validation errors
     * @throws \PeskyORM\Exception\DbObjectException
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
            throw new DbObjectException($this, "Unknown DbObject field or relation alias [$fieldName]");
        }
    }

    /**
     * @param string $fieldName
     * @return mixed|null
     * @throws DbObjectFieldException
     * @throws DbObjectException
     */
    public function _getFieldValue($fieldName) {
        $field = $this->_getField($fieldName);
        if ($field->isFile() && !$field->hasValue()) {
            return $field->hasFile() ? $field->getFileInfo(true, true) : null;
        } else if ($this->isSuffixedField($fieldName)) {
            if (
                preg_match('%^(.+)_(date|time|ts)$%is', $fieldName, $matches)
                && $field instanceof TimestampField
            ) {
                switch ($matches[2]) {
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
            } else if (
                preg_match('%^(.+)_(ts)$%is', $fieldName, $matches)
                && $field instanceof DateField
            ) {
                return $field->getUnixTimestamp();
            } else if (
                preg_match('%^(.+)_(arr|array)$%is', $fieldName, $matches)
                && $field instanceof JsonField
            ) {
                return $field->getArray();
            } else {
                throw new DbObjectException($this, "Unknown DbObject field or relation alias [$fieldName]");
            }
        } else {
            return $field->getValue();
        }
    }

    /**
     * @param string $fieldName
     * @param mixed $newValue
     * @param bool $isDbValue
     * @return $this
     * @throws DbObjectFieldException
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
     * @throws \PeskyORM\Exception\DbObjectException
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
            $this->readRelations([$fieldNameOrAlias]);
            $object = $this->_getRelatedObject($fieldNameOrAlias, true);
            return is_array($object) ? count($object) > 0 : $object->exists();
        } else {
            throw new DbObjectException($this, "Unknown DbObject field or relation alias [$fieldNameOrAlias]");
        }
    }

    /**
     * @param string $alias
     * @return bool
     * @throws \PeskyORM\Exception\DbTableConfigException
     * @throws \PeskyORM\Exception\DbObjectException
     */
    protected function _hasRelatedObject($alias, $returnTrueForNotInitiatedHasMany = false) {
        $relation = $this->_getRelationConfig($alias);
        $relatedObject = $this->_relatedObjects[$alias];
        if ($relation->getType() === DbRelationConfig::HAS_MANY) {
            return $returnTrueForNotInitiatedHasMany || ($relatedObject !== false);
        } else {
            // 1:1 relation
            if (empty($relatedObject)) {
                return false;
            } else if ($relatedObject->exists()) {
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
     * @throws \PeskyORM\Exception\DbObjectException
     */
    public function begin($withRelations = false) {
        $this->cleanUpdatesOfFields();
        $this->_isStoringFieldUpdates = true;
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
     * @throws \PeskyORM\Exception\DbException
     * @throws DbObjectException
     * @throws DbObjectValidationException
     */
    public function commit($commitRelations = false) {
        $ret = true;
        if (!empty($this->_updatedFields)) {
            if (!$this->_isStoringFieldUpdates && count($this->_updatedFields) > 1) {
                throw new DbObjectException($this, 'Attempt to commit() several field updates without calling begin()');
            }
            $ret = $this->saveUpdates($this->_updatedFields);
        }
        if ($ret) {
            $this->cleanUpdatesOfFields();
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
     * @throws \PeskyORM\Exception\DbObjectFieldException
     * @throws \PeskyORM\Exception\DbObjectException
     */
    public function rollback($rollbackRelations = false) {
        // restore db object state before begin()
        $this->_isStoringFieldUpdates = false;
        foreach ($this->_updatedFields as $fieldName) {
            $this->_getField($fieldName)->restoreDdValueOrReset();
        }
        $this->cleanUpdatesOfFields();
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
     * @throws \PeskyORM\Exception\DbObjectException
     */
    public function fieldUpdated($fieldName) {
        if ($this->_allowFieldsUpdatesTracking) {
            $this->_updatedFields[] = $fieldName;
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
     * @throws \PeskyORM\Exception\DbObjectFieldException
     * @throws \PeskyORM\Exception\DbObjectException
     */
    public function read($pkValue, $fieldNames = '*', $relations = false) {
        $this->_setFieldValue($this->_getPkFieldName(), $pkValue);
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
     * @throws \PeskyORM\Exception\DbObjectException
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
     * @throws \PeskyORM\Exception\DbObjectException
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
     * @return DbObject|DbObject[]
     * @throws \PeskyORM\Exception\DbUtilsException
     * @throws \PeskyORM\Exception\DbTableConfigException
     * @throws \PeskyORM\Exception\DbQueryException
     * @throws \PeskyORM\Exception\DbModelException
     * @throws \PeskyORM\Exception\DbException
     * @throws DbObjectException when local field is empty but required
     */
    protected function _findRelatedObject($relationAlias) {
        $localFieldName = $this->_getLocalFieldNameForRelation($relationAlias);
        $relationType = $this->_getTypeOfRealation($relationAlias);
        if ($this->_isFieldHasEmptyValue($localFieldName)) {
            if (!$this->_getField($localFieldName)->canBeNull()) {
                // local field empty - bad situation
                throw new DbObjectException($this, "Cannot find related object [{$relationAlias}] [{$this->_getModelAlias()}->{$localFieldName}] is empty");
            } else {
                if ($relationType === DbRelationConfig::HAS_MANY) {
                    $this->_relatedObjects[$relationAlias] = array();
                } else {
                    $this->_relatedObjects[$relationAlias] = $this->_initRelatedObject($relationAlias);
                }
            }
        } else {
            // load object[s]
            $conditions = $this->_insertDataIntoRelationConditions($relationAlias);
            if ($relationType === DbRelationConfig::HAS_MANY) {
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
                $relatedObject->find($conditions);
                if ($relatedObject->exists() && $relationType !== DbRelationConfig::BELONGS_TO) {
                    $relatedObject->linkTo($this);
                }
                $relatedObject->_getModel()->setAlias($modelAliasBak); //< restore model alias to default value
            }
        }
        return $this->_relatedObjects[$relationAlias];
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
     * @throws \PeskyORM\Exception\DbUtilsException
     * @throws \PeskyORM\Exception\DbTableConfigException
     * @throws \PeskyORM\Exception\DbQueryException
     * @throws \PeskyORM\Exception\DbModelException
     * @throws \PeskyORM\Exception\DbException
     * @throws \PeskyORM\Exception\DbObjectException
     */
    public function find($conditions, $fieldNames = '*', $relations = array()) {
        if (is_array($fieldNames) && !in_array($this->_getPkFieldName(), $fieldNames)) {
            $fieldNames[] = $this->_getPkFieldName();
        } else if (!is_array($fieldNames) && $fieldNames !== '*') {
            $fieldNames = array($this->_getPkFieldName(), $fieldNames);
        }
        $data = $this->_getModel()->selectOne($fieldNames, $conditions, false, false);
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
                $dbField->valueWasSavedToDb();
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
     * @throws \PeskyORM\Exception\DbTableConfigException
     * @throws \PeskyORM\Exception\DbQueryException
     * @throws \PeskyORM\Exception\DbModelException
     * @throws \PeskyORM\Exception\DbException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\DbObjectFieldException
     * @throws DbObjectException
     * @throws DbObjectValidationException
     */
    public function save($verifyDbExistance = false, $createIfNotExists = false, $saveRelations = false) {
        if ($this->_isStoringFieldUpdates) {
            throw new DbObjectException($this, 'Calling DbObject->save() after DbObject->begin() that was not commited or rejected');
        }
        $errors = $this->validate(null, true);
        if (!empty($errors)) {
            throw new DbObjectValidationException($this, $errors);
        }
        $localTransaction = false;
        $model = $this->_getModel();
        if (!$model->inTransaction()) {
            $model->begin();
            $localTransaction = true;
        }
        $exists = $this->exists($verifyDbExistance);
        if ($verifyDbExistance && !$exists && !$createIfNotExists) {
            $this->_getPkField()->setValidationError('@!db.error_edit_not_existing_record@');
            if ($localTransaction) {
                $model->rollback();
            }
            return false;
        }
        $relatedObjects = !empty($saveRelations)
            ? $this->getRelationsToSave($saveRelations)
            : array();
        try {
            if (!$exists) {
                $this->_allowFieldsUpdatesTracking = false;
                $ret = $model->insert($this->getDataForSave(), '*');
                if (!empty($ret)) {
                    $this->_updateWithDbValues($ret);
                }
                $this->_allowFieldsUpdatesTracking = true;
            } else {
                $dataToSave = $this->getDataForSave(null, true);
                unset($dataToSave[$this->_getPkFieldName()]);
                if (!empty($dataToSave)) {
                    $this->_allowFieldsUpdatesTracking = false;
                    $ret = $model->update($dataToSave, $this->getFindByPkConditions(), '*');
                    if (!empty($ret) && count($ret) === 1) {
                        $ret = $ret[0];
                        $this->_updateWithDbValues($ret);
                    } else if (count($ret) > 1) {
                        $model->rollback();
                        throw new DbObjectException($this, 'Attempt to update [' . count($ret) . '] records instead of 1: ' . $model->getLastQuery());
                    }
                    $this->_allowFieldsUpdatesTracking = true;
                } else {
                    // nothing to update
                    $ret = true;
                }
            }
        } catch (\PDOException $exc) {
            $model->rollback();
            throw $exc;
        }
        if (!empty($ret)) {
            // save attached files
            if ($this->_hasFileFields()) {
                $this->saveFiles();
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
            $this->cleanUpdatesOfFields();
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
     * @throws DbObjectFieldException
     * @throws DbObjectException
     */
    protected function saveFiles($fieldNames = null) {
        if (empty($fieldNames) || !is_array($fieldNames)) {
            $fieldNames = array_keys($this->_getFileFieldsConfigs());
        } else {
            $fieldNames = array_intersect(array_keys($this->_getFileFieldsConfigs()), $fieldNames);
        }
        if (!empty($fieldNames)) {
            if (!$this->existsInDb()) {
                throw new DbObjectException($this, 'Unable to save files of non-existing object');
            }
            foreach ($fieldNames as $fieldName) {
                $this->_getFileField($fieldName)->saveUploadedFile();
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
     * @return bool
     * @throws \PeskyORM\Exception\DbTableConfigException
     * @throws \PeskyORM\Exception\DbQueryException
     * @throws \PeskyORM\Exception\DbModelException
     * @throws \PDOException
     * @throws \PeskyORM\Exception\DbObjectFieldException
     * @throws \PeskyORM\Exception\DbException
     * @throws DbObjectException
     * @throws DbObjectValidationException
     */
    public function saveUpdates($fieldNames = null) {
        if (!$this->exists()) {
            throw new DbObjectException($this, 'Unable to update non-existing object');
        }
        $localTransaction = false;
        if (empty($fieldNames)) {
            $fieldNames = null;
        } else if (is_string($fieldNames)) {
            $fieldNames = array($fieldNames);
        } else if (!is_array($fieldNames)) {
            throw new DbObjectException($this, '$fieldNames argument should contain only array, string or null');
        }
        $errors = $this->validate($fieldNames, true);
        if (!empty($errors)) {
            throw new DbObjectValidationException($this, $errors);
        }
        $model = $this->_getModel();
        if (!$model->inTransaction()) {
            $model->begin();
            $localTransaction = true;
        }
        $dataToSave = $this->getDataForSave($fieldNames, true);
        unset($dataToSave[$this->_getPkFieldName()]);
        if (!empty($dataToSave)) {
            try {
                $this->_allowFieldsUpdatesTracking = false;
                $ret = $model->update($dataToSave, $this->getFindByPkConditions(), '*');
                if (!empty($ret)) {
                    if (count($ret) === 1) {
                        $ret = $ret[0];
                        $this->_updateWithDbValues($ret);
                    } else if (count($ret) > 1) {
                        $model->rollback();
                        throw new DbObjectException($this, 'Attempt to update [' . count($ret) . '] records instead of 1: ' . $model->getLastQuery());
                    }
                }
                $this->_allowFieldsUpdatesTracking = true;
            } catch (\PDOException $exc) {
                $model->rollback();
                throw $exc;
            }
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
        if (!empty($ret)) {
            $this->cleanUpdatesOfFields();
            $this->afterSave(false);
        }
        return !empty($ret);
    }

    /**
     * Delete current object
     * @param bool $resetFields - true: will reset DbFields (default) | false: only primary key will be reset
     * @param bool $ignoreIfNotExists - true: will not throw exception if object not exists
     * @return $this
     * @throws \PeskyORM\Exception\DbTableConfigException
     * @throws \PeskyORM\Exception\DbQueryException
     * @throws \PeskyORM\Exception\DbModelException
     * @throws \PeskyORM\Exception\DbException
     * @throws \PDOException
     * @throws DbObjectException
     * @throws \Exception
     */
    public function delete($resetFields = true, $ignoreIfNotExists = false) {
        if (!$this->exists()) {
            if (!$ignoreIfNotExists) {
                throw new DbObjectException($this, 'Unable to delete non-existing object');
            }
        } else {
            $model = $this->_getModel();
            $alreadyInTransaction = $model->inTransaction();
            if (!$alreadyInTransaction) {
                $model->begin();
            }
            try {
                $model->delete($this->getFindByPkConditions());
            } catch (\PDOException $exc) {
                $model->rollback();
                throw $exc;
            }
            $this->afterDelete(); //< transaction can be closed there
            if (!$alreadyInTransaction && $model->inTransaction()) {
                $model->commit();
            }
            if (!$this->_dontDeleteFiles && $this->_hasFileFields()) {
                $this->deleteFilesAfterObjectDeleted();
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
     * @throws \PeskyORM\Exception\DbObjectException
     * @throws \PeskyORM\Exception\DbObjectFieldException
     */
    public function deleteFilesAfterObjectDeleted() {
        if (!$this->exists()) {
            return false;
        }
        foreach ($this->_getFileFieldsConfigs() as $fieldName => $tableColumnConfig) {
            Folder::remove($this->_getFileField($fieldName)->getFileDirPath());
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
     * Check if object has not empty PK value
     * @param bool $testIfReceivedFromDb = true: also test if DbField->isValueReceivedFromDb() returns true
     * @return bool
     * @throws DbObjectException
     */
    public function exists($testIfReceivedFromDb = false) {
        $pkSet = $this->_getPkField()->hasNotEmptyValue();
        if (!$pkSet) {
            return false;
        }
        if ($testIfReceivedFromDb) {
            return $this->_getPkField()->isValueReceivedFromDb();
        }
        return true;
    }

    /**
     * Throw exception if object not exists
     * @throws DbObjectException
     */
    public function requireExistence() {
        if (!$this->exists()) {
            throw new DbObjectException($this, self::ERROR_OBJECT_NOT_EXISTS);
        }
    }

    /**
     * Check if record exists in DB. Uses DB Query to verify existence
     * @return bool
     * @throws DbObjectException
     */
    public function existsInDb() {
        $pkSet = $this->_getPkField()->hasNotEmptyValue();
        $exists = $pkSet && $this->_getModel()->exists($this->getFindByPkConditions());
        if ($exists) {
            $this->_getPkField()->setValueReceivedFromDb(true);
        }
        return $exists;
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
     * Needed to make on-save modifications to data.
     * Be careful - data will not be validated
     * @param null $fieldNames
     * @param bool $onlyUpdatedFields
     * @return array
     * @throws DbObjectException
     */
    protected function getDataForSave($fieldNames = null, $onlyUpdatedFields = false) {
        return static::toStrictArray($fieldNames, $onlyUpdatedFields);
    }

    /**
     * Collect available values into associative array (does not validate field values)
     * Used to just get values from object. Also can be overwritten in child classes to add/remove specific values
     * @param array|null $fieldNames - will return only this fields (if not skipped)
     *      Note: pk field added automatically if object has it
     * @param array|string|bool $relations - array and string: relations to return | true: all relations | false: without relations
     * @param bool $forceRelationsRead - true: relations will be read before processing | false: only previously read relations will be returned
     * @return array
     * @throws \PeskyORM\Exception\DbObjectFieldException
     * @throws \PeskyORM\Exception\DbObjectException
     */
    public function toPublicArray($fieldNames = null, $relations = false, $forceRelationsRead = true) {
        $values = array();
        $fieldNames = $this->resolveFieldsListForToPublicArray($fieldNames);
        foreach ($fieldNames as $fieldName) {
            $field = $this->_getField($fieldName);
            if ($field->isPrivate()) {
                continue;
            } else if ($field->isFile()) {
                if ($this->exists() && $field->hasFile()) {
                    $values[$fieldName] = $field->getFileInfo(true, true)->toPublicArray();
                } else {
                    $values[$fieldName] = null;
                }
            } else if ($field->hasValue()) {
                $values[$fieldName] = $field->getValue();
            }
        }

        return $values + $this->relationsToPublicArray($relations, $forceRelationsRead);
    }

    public function toPublicArrayWithoutFiles($fieldNames = null, $relations = false, $forceRelationsRead = true) {
        $values = array();
        $fieldNames = $this->resolveFieldsListForToPublicArray($fieldNames);
        foreach ($fieldNames as $fieldName) {
            $field = $this->_getField($fieldName);
            if ($field->isPrivate() || $field->isFile()) {
                continue;
            } else if ($field->isFile()) {
                continue;
            } else if ($field->hasValue()) {
                $values[$fieldName] = $field->getValue();
            }
        }

        return $values + $this->relationsToPublicArray($relations, $forceRelationsRead);
    }

    protected function resolveFieldsListForToPublicArray($fieldNames) {
        if (empty($fieldNames) || !is_array($fieldNames)) {
            $fieldNames = $this->_allFieldNames;
        }
        if ($this->_autoAddPkValueToPublicArray && $this->exists() && !in_array($this->_getPkFieldName(), $fieldNames)) {
            $fieldNames[] = $this->_getPkFieldName();
        }
        return $fieldNames;
    }

    /**
     * @param null|string|array $relations
     * @param bool $forceRelationsRead
     * @param bool $withImages
     * @return array
     * @throws DbObjectException
     */
    public function relationsToPublicArray($relations = null, $forceRelationsRead = true, $withImages = true) {
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
            if ($forceRelationsRead) {
                $this->_findRelatedObject($relationAlias); //< read relation data
            }
            // show related object if it is set or
            $relatedObjects = $this->_relatedObjects[$relationAlias];
            if (!empty($relatedObjects)) {
                if (is_array($relatedObjects)) {
                    $return[$relationAlias] = [];
                    /** @var DbObject $object */
                    foreach ($relatedObjects as $object) {
                        $return[$relationAlias][] = $withImages
                            ? $object->toPublicArray($fieldNames, false)
                            : $object->toPublicArrayWithoutFiles($fieldNames, false);
                    }
                } else if ($relatedObjects->exists()) {
                    $return[$relationAlias] = $withImages
                        ? $relatedObjects->toPublicArray($fieldNames, false)
                        : $relatedObjects->toPublicArrayWithoutFiles($fieldNames, false);
                } else {
                    $return[$relationAlias] = null;
                }
            }
        }
        return $return;
    }

    /**
     * Collect default values for the fields
     * @param array|null $fieldNames - will return only this fields (if not skipped)
     * @param bool $addExcludedFields - true: if field is excluded for all actions - it will not be returned
     * @return array
     * @throws DbObjectFieldException
     * @throws DbObjectException
     */
    public function getDefaultsArray($fieldNames = null, $ignoreExcludedFields = true) {
        $values = array();
        if (is_string($fieldNames)) {
            $fieldNames = array($fieldNames);
        } else if (empty($fieldNames)) {
            $fieldNames = $this->_allFieldNames;
        } else if (!is_array($fieldNames)) {
            throw new DbObjectException($this, "getDefaultsArray: \$fieldNames argument must be empty, string or array");
        }
        foreach ($fieldNames as $name) {
            $field = $this->_getField($name);
            if (!$ignoreExcludedFields || !$field->isExcludedForAllActions()) {
                $values[$name] = $this->_getField($name)->getDefaultValueOr(null);
            }
        }
        return $values;
    }

    /**
     * Collect values of all fields avoiding exceptions
     * Fields that were not set will be ignored
     * @return array
     * @throws \PeskyORM\Exception\DbObjectFieldException
     * @throws \PeskyORM\Exception\DbObjectException
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

}