<?php

namespace PeskyORM;
use PeskyORM\ORM\Record;

/**
 * Class DbObject
 */
class DbObject extends Record {
    
    /**
     * @var string
     */
    static protected $_baseModelClass = DbModel::class;
    
    /**
     * @param null|array|string|int $dataOrPkValue - null: do nothing | int and string: is primary key (read db) | array: object data
     * @param bool $ignoreUnknownData - used only when $data not empty and is array
     *      true: filters $data that does not belong t0o this object
     *      false: $data that does not belong to this object will trigger exceptions
     * @param bool $isDbValues - true: indicates that field values passsed via $data as array are db values
     * @param null $__deprecated
     * @return $this
     */
    static public function create($dataOrPkValue = null, bool $ignoreUnknownData = false, bool $isDbValues = false, $__deprecated = null) {
        $record = new static();
    
        if (!empty($dataOrPkValue)) {
            if (is_array($dataOrPkValue)) {
                $record->fromData($dataOrPkValue, $isDbValues, !$ignoreUnknownData);
            } else {
                $record->fromPrimaryKey($dataOrPkValue);
            }
        }
        return $record;
    }

    /**
     * Read data from DB using Model
     * @param string|array $conditions - conditions to use
     * @param array|string $fieldNames - list of fields to get
     * @param array|null|string $relations - list of relations to read with object
     * @return $this
     */
    static public function search($conditions, $fieldNames = '*', $relations = array()) {
        return self::find($conditions, $fieldNames, $relations);
    }

    /**
     * @deprecated
     * @return \PeskyORM\DbTableConfig
     */
    public function _getTableConfig() {
        return $this->_getModel()->getTableConfig();
    }

    /**
     * @deprecated
     * @return mixed
     */
    public function _getPkFieldName() {
        return static::getPrimaryKeyColumnName();
    }

    /**
     * @deprecated
     * @return mixed
     */
    public function _getPkValue() {
        return $this->getPrimaryKeyValue();
    }

    /**
     * @deprecated
     * @return DbModel
     */
    public function _getModel() {
        return static::getTable();
    }
    
    public static function getTable() {
        /** @var DbModel $baseModelClass */
        $baseModelClass = static::$_baseModelClass;
        return $baseModelClass::getModelByObjectClass(static::class);
    }

    /**
     * @deprecated
     * @param string $fieldName
     * @return mixed|null
     */
    public function _getFieldValue($fieldName) {
        return $this->getValue($fieldName);
    }

    /**
     * @deprecated
     * @param string $fieldName
     * @param mixed $newValue
     * @param bool $isDbValue
     * @return $this
     */
    public function _setFieldValue($fieldName, $newValue, $isDbValue = false) {
        return $this->updateValue($fieldName, $newValue, $isDbValue);
    }

    /**
     * @deprecated
     * Check if object has not empty PK value
     * @param bool $testIfReceivedFromDb = true: also test if DbField->isValueReceivedFromDb() returns true
     * @return bool
     */
    public function exists($testIfReceivedFromDb = false) {
        return $this->existsInDb($testIfReceivedFromDb);
    }

    /**
     * @deprecated
     * Collect available values into associative array (does not validate field values)
     * Used to just get values from object. Also can be overwritten in child classes to add/remove specific values
     * @param array|null $fieldNames - will return only this fields (if not skipped)
     *      Note: pk field added automatically if object has it
     * @param array|string|bool $relations - array and string: relations to return | true: all relations | false: without relations
     * @param bool $forceRelationsRead - true: relations will be read before processing | false: only previously read relations will be returned
     * @return array
     */
    public function toPublicArray($fieldNames = null, $relations = false, $forceRelationsRead = true) {
        if ($relations === true) {
            $relations = ['*'];
        }
        if ($fieldNames === null) {
            $fieldNames = [];
        }
        return $this->toArray((array)$fieldNames, $relations ? (array)$relations : [], $forceRelationsRead, true);
    }
    
    /**
     * @deprecated
     * Collect available values into associative array (does not validate field values)
     * Used to just get values from object. Also can be overwritten in child classes to add/remove specific values
     * @param array|null $fieldNames - will return only this fields (if not skipped)
     *      Note: pk field added automatically if object has it
     * @param array|string|bool $relations - array and string: relations to return | true: all relations | false: without relations
     * @param bool $forceRelationsRead - true: relations will be read before processing | false: only previously read relations will be returned
     * @return array
     */
    public function toPublicArrayWithoutFiles($fieldNames = null, $relations = false, $forceRelationsRead = true) {
        if ($relations === true) {
            $relations = ['*'];
        }
        return $this->toArray((array)$fieldNames, $relations ? (array)$relations : [], $forceRelationsRead, false);
    }

    /**
     * @deprecated
     * Collect default values for the fields
     * @param array|null|string $fieldNames - will return only this fields (if not skipped)
     * @param bool $addExcludedFields - true: if field is excluded for all actions - it will not be returned
     * @return array
     */
    public function getDefaultsArray($fieldNames = null, $ignoreExcludedFields = true) {
        return $this->getDefaults($fieldNames, $ignoreExcludedFields, true);
    }

}