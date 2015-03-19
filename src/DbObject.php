<?php

namespace PeskyORM;
use ORM\DbColumnConfig;
use ORM\DbRelationConfig;
use PeskyORM\Exception\DbObjectException;
use PeskyORM\Lib\File;
use PeskyORM\Lib\Folder;
use PeskyORM\Lib\ImageUtils;

/**
 * Class DbObject
 * @property array $validationErrors
 */
class DbObject {

    /**
     * @var bool
     * true: do not delete attached files in DbObject->delete()
     */
    public $dontDeleteFiles = false;

    /**
     * associative list of DbObjectField
     * @var DbObjectField[]
     */
    public $_fields = array();
    /**
     * associative list that maps related object alias to its class name
     * @var string[]
     */
    public $_aliasToClassName = array();
    /**
     * associative list that maps related object alias to current object's field
     * @var string[]
     */
    public $_aliasToLocalField = array();
    /**
     * associative list that maps related object alias to related object field name
     * @var array
     */
    public $_aliasToRelationField = array();
    /**
     * associative list that maps related object alias to relation join conditions
     * @var array
     */
    public $_aliasToJoinConditions = array();
    /**
     * associative list that maps related object alias to relation type
     * relation types: 'one', 'many', 'belong'
     * @var array
     */
    public $_aliasToRelationType = array();

    /**
     * associative list that maps related object aliases to their DbObject (has one / belongs to) or array of DbObject (has many)
     * @var DbObject[]|array[DbObject]
     */
    public $_relatedObjects = array();
    // todo: assign this object into relations of related objects to implement parent<->child links

    /**
     * @var DbModel
     */
    public $model;
    public $customErrors = array();
    protected $hasFiles = false;
    protected $fileFields = array();

    protected $existsInDb = -1;

    // for begin(), commit() and rollback()
    protected $allowFieldsUpdatesTracking = true;
    protected $updatedFields = array();
    protected $storeFieldUpdates = false;
    /** @var null|array */
    protected $dataBackup = null;
    /**
     * used by forms to remember some data before it was changed
     * Use case: form allows changing of primary key but save() requires old pk value to manage changes
     * @var null|array
     */
    public $originalData = null;
    /**
     * Key name in $data array passed to _fromData()
     * @var string
     */
    public $originalDataKey = '_backup';

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
        $this->model = $model;
        // initiate DbObjectField for all fields
        foreach ($this->model->fields as $name => $info) {
            $this->_fields[$name] = new DbObjectField($this, $name, $info);
            if ($this->_fields[$name]->isFile) {
                $this->hasFiles = true;
                $this->fileFields[] = $name;
            }
        }
        // test if primary key provided
        if (empty($this->_fields[$this->model->getPkColumn()])) {
            throw new DbObjectException($this, "Primary key field [{$this->model->getPkColumn()}] not found in Model->fields");
        }
        // mark and modify pk field
        $this->_fields[$this->model->getPkColumn()]->isPk = true;
        $this->_fields[$this->model->getPkColumn()]->required = false;
        $this->_fields[$this->model->getPkColumn()]->null = true;
        // initiate related objects
        /** @var DbRelationConfig $settings */
        foreach ($this->model->relations as $alias => $settings) {
            $this->_aliasToLocalField[$alias] = $settings->getColumn();
            $this->_fields[$this->_aliasToLocalField[$alias]]->addRelation($alias);
            $this->_aliasToJoinConditions[$alias] = $this->buildRelationJoinConditions($alias, $settings);
            $this->_aliasToRelationField[$alias] = $settings->getForeignColumn();
            $this->_aliasToClassName[$alias] = $this->model->getFullDbObjectClass($settings->getForeignTable());
            $this->_aliasToRelationType[$alias] = $settings->getType();
        }
        $this->cleanRelatedObjects();
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
     * Build conditions similar to JOIN ON conditions
     * @param string $relationAlias
     * @param DbRelationConfig $relationSettings
     * @return array
     */
    protected function buildRelationJoinConditions($relationAlias, DbRelationConfig $relationSettings) {
        $conditions = $this->model->getAdditionalConditionsForRelation($relationAlias);
        $conditions[$relationAlias . '.' . $relationSettings->getForeignColumn()] = $this->model->getAlias() . '.' . $relationSettings->getColumn();
        return $conditions;
    }

    /**
     * Insert values into conditions instead of "$this->alias"."field_name" and "field_name"
     * @param $conditions
     * @return array
     */
    protected function insertDataIntoRelationConditions($conditions) {
        $dbObject = $this;
        $replacer = function ($matches) use ($dbObject) {
            if (isset($dbObject->_fields[$matches[2]])) {
                return $dbObject->_fields[$matches[2]]->value;
            } else {
                return $matches[0];
            }
        };
        $newConditions = array();
        foreach ($conditions as $key => $value) {
            $key = preg_replace_callback("%`?({$this->model->getAlias()})`?\.`?([a-zA-Z0-9_]+)`?%is", $replacer, $key);
            $key = preg_replace_callback("%(?:^|\s)(`?)([a-zA-Z0-9_]+)`?%is", $replacer, $key);
            $value = preg_replace_callback("%`?({$this->model->getAlias()})`?\.`?([a-zA-Z0-9_]+)`?%is", $replacer, $value);
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
    protected function cleanRelatedObject($alias) {
        if (!isset($this->_aliasToLocalField[$alias])) {
            throw new DbObjectException($this, "Unknown relation with alias [$alias]");
        }
        $this->_relatedObjects[$alias] = false;
    }

    /**
     * Clean all related objects
     */
    protected function cleanRelatedObjects() {
        foreach ($this->model->relations as $alias => $settings) {
            $this->_relatedObjects[$alias] = false;
        }
    }

    /**
     * Init related object by $alias
     * @param string $alias
     * @param null|DbObject|DbObject[] $object
     * @param bool $ignorePkNotSetError - true: exception '[local_field] is empty' when local_field is primary key will be ignored
     * @return bool|$this[]|$this - false: for hasMany relation
     * @throws DbObjectException
     */
    protected function initRelatedObject($alias, $object = null, $ignorePkNotSetError = false) {
        if (empty($this->model->relations[$alias])) {
            throw new DbObjectException($this, "Unknown relation with alias [$alias]");
        }
        $settings = $this->model->relations[$alias];
        $localField = $this->_aliasToLocalField[$alias];
        if (empty($this->_fields[$localField])) {
            throw new DbObjectException($this, "Relation [$alias] points to unknown field [{$settings->getColumn()}]");
        }
        // check if passed object is valid
        if (!empty($settings->getType() === DbRelationConfig::HAS_MANY)) {
            $this->initOneToManyRelation($alias, $object, $ignorePkNotSetError);
        } else {
            $this->initOneToOneRelation($alias, $object, $ignorePkNotSetError);
        }
        return $this->_relatedObjects[$alias];
    }

    /**
     * @param string $alias
     * @param array|DbObject|null $object
     * @param bool $ignorePkNotSetError
     * @throws DbObjectException
     */
    protected function initOneToOneRelation($alias, $object, $ignorePkNotSetError = false) {
        $settings = $this->model->relations[$alias];
        $localField = $this->_aliasToLocalField[$alias];
        $relationField = $this->_aliasToRelationField[$alias];
        $localFieldIsPrimaryKey = $this->$localField == $this->model->getPkColumn();
        if (empty($this->$localField)) {
            $relationFieldValue = false;
            if (is_array($object) && !empty($object[$relationField])) {
                $relationFieldValue = $object[$relationField];
            } else if (is_object($object) && !empty($object->$relationField)) {
                $relationFieldValue = $object->$relationField;
            }
            if ($localFieldIsPrimaryKey || empty($relationFieldValue)) {
                if ($localFieldIsPrimaryKey || $this->_fields[$localField]->required) {
                    // local field is empty and is required or is primary key
                    throw new DbObjectException($this, "Cannot link [{$this->model->getAlias()}] with [{$alias}]: [{$this->model->getAlias()}->{$localField}] and {$alias}->{$relationField} are empty");
                }
            } else {
                $this->$localField = $relationFieldValue;
            }
        }
        if (empty($object)) {
            // init empty object
            $this->_relatedObjects[$alias] = $this->model->getDbObject($settings->getForeignTable());
            // and link it to current object
            if (!empty($this->$localField)) {
                $this->_relatedObjects[$alias]->_fields[$relationField]->default = $this->$localField;
                $this->_relatedObjects[$alias]->$relationField = $this->$localField;
            }
        } else {
            if (is_object($object)) {
                $class = get_class($object);
                if (trim($class, '\\') != trim($this->_aliasToClassName[$alias], '\\')) {
                    throw new DbObjectException($this, "Trying to assign object of class [$class] as object of class [{$this->_aliasToClassName[$alias]}]");
                }
                $this->_relatedObjects[$alias] = $object;
            } else {
                // data array or id
                $this->_relatedObjects[$alias] = $this->model->getDbObject($settings->getForeignTable(), $object);
            }
            // validate relation
            $valid = $this->validateRelationData($alias, $ignorePkNotSetError);
            if (!$valid) {
                if ($valid === false && empty($this->_relatedObjects[$alias]->$relationField)) {
                    // and link to current object
                    if (!empty($this->$localField)) {
                        $this->_relatedObjects[$alias]->_fields[$relationField]->default = $this->$localField;
                        $this->_relatedObjects[$alias]->$relationField = $this->$localField;
                    }
                } else {
                    throw new DbObjectException($this, "Related object [$alias] does not belong to Current one");
                }
            }
        }
    }

    protected function initOneToManyRelation($alias, $object, $ignorePkNotSetError = false) {
        // todo: implement DbObjectCollection that works as usual array but has some useful methods like find/sort/filter
        $settings = $this->model->relations[$alias];
        $localField = $this->_aliasToLocalField[$alias];
        $relationField = $this->_aliasToRelationField[$alias];
        if (empty($this->$localField)) {
            throw new DbObjectException($this, "Cannot link [{$this->model->getAlias()}] with [{$alias}]: [{$this->model->getAlias()}->{$localField}] is empty");
        }
        if (!empty($object) && is_array($object) && isset($object[0])) {
            $this->_relatedObjects[$alias] = array();
            foreach ($object as $item) {
                if (is_object($item)) {
                    // array of db objects
                    if (!empty($item) && get_class($item) !== $this->_aliasToClassName[$item]) {
                        $class = get_class($item);
                        throw new DbObjectException($this, "Trying to assign object of class [$class] as object of class [{$this->_aliasToClassName[$alias]}]");
                    } else {
                        $this->_relatedObjects[$alias][] = $item;
                    }
                } else {
                    // array of item data arrays or item ids
                    $this->_relatedObjects[$alias][] = $this->model->getDbObject($settings->getForeignTable(), $item);
                }
            }
            // validate relation
            $valid = $this->validateRelationData($alias, $ignorePkNotSetError);
            if (!$valid) {
                if ($valid === false && empty($this->_relatedObjects[$alias]->$relationField)) {
                    // and link to current object but don't save
                    foreach ($this->_relatedObjects[$alias] as &$object) {
                        $object->$relationField = $this->$localField;
                    }
                } else {
                    throw new DbObjectException($this, "Related object [$alias] does not belong to Current one");
                }
            }
        } else {
            $this->_relatedObjects[$alias] = false; //< means not loaded
        }
    }

    public function hasFiles() {
        return $this->hasFiles;
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
     * @param array $data
     * @param bool|array $filter -
     *      true: filters data that does not belong to this object
     *      false: data that does not belong to this object will trigger exceptions
     *      array: list of fields to use
     * @return $this
     * @throws DbObjectException when $filter == false and unknown field detected in $data
     */
    public function updateData($data, $filter = false) {
        if ($this->exists()) {
            $data[$this->model->getPkColumn()] = $this->pkValue();
        }
        $this->_fromData($data, $filter, false);
        return $this;
    }

    /**
     * Update fields from $data and mark them as they are gathered from db (used in save())
     * @param array $data
     * @return $this
     */
    protected function updateWithDbData($data) {
        foreach ($data as $key => $value) {
            $this->_fields[$key]->value = $value;
            $this->_fields[$key]->isDbValue = true;
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
    protected function fromDbData($data, $filter = false) {
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
        if (
            !$this->exists()
            || !isset($data[$this->model->getPkColumn()])
            || $data[$this->model->getPkColumn()] != $this->pkValue()
        ) {
            $this->reset();
        }
        if (is_array($data)) {
            // remember original data
            if (!empty($data[$this->originalDataKey])) {
                $this->originalData = json_decode($data[$this->originalDataKey], true);
                unset($data[$this->originalDataKey]);
            }
            // filter fields
            if (is_array($filter)) {
                $data = array_intersect_key($data, array_flip($filter));
                $filter = false;
            }
            // set primary key first
            if (isset($data[$this->model->getPkColumn()])) {
                $this->{$this->model->getPkColumn()} = $data[$this->model->getPkColumn()];
                if ($isDbValue) {
                    $this->_fields[$this->model->getPkColumn()]->isDbValue = true;
                } //< $isDbValue == false handled by value setter
                unset($data[$this->model->getPkColumn()]);
            }
            foreach ($data as $fieldNameOrAlias => $value) {
                if (array_key_exists($fieldNameOrAlias, $this->model->fields)) {
                    $this->_fields[$fieldNameOrAlias]->value = $value;
                    if ($isDbValue) {
                        $this->_fields[$fieldNameOrAlias]->isDbValue = true;
                    } //< $isDbValue == false handled by value setter
                } else if (isset($this->_aliasToLocalField[$fieldNameOrAlias]) && is_array($value)) {
                    if (!empty($this->_relatedObjects[$fieldNameOrAlias])) {
                        // update related object
                        $this->_relatedObjects[$fieldNameOrAlias]->updateData($value);
                    } else {
                        // init new one
                        $this->initRelatedObject($fieldNameOrAlias, $value, true);
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
        $this->existsInDb = -1;
        $this->customErrors = array();
        $this->originalData = null;
        $this->storeFieldUpdates = false;
        foreach ($this->model->fields as $name => $null) {
            $this->_fields[$name]->reset();
        }
        $this->dataBackup = null;
        $this->updatedFields = array();
        $this->cleanRelatedObjects();
        return $this;
    }

    /**
     * Validate passed $fields or all fields if $fields is empty
     * @param null|string|array $fields - empty: all fields | string: single field | array: only this fields
     * @param bool $forSave - true: allows some specific validations lise isUnique
     * @return array - validation errors
     */
    public function validate($fields = null, $forSave = false) {
        if (empty($fields) || (!is_array($fields) && !is_string($fields))) {
            $fields = array_keys($this->model->fields);
        } else if (is_string($fields)) {
            $fields = array($fields);
        }
        $errors = array();
        foreach ($fields as $name) {
            if (!$this->_fields[$name]->validate(true, $forSave)) {
                $errors[$name] = $this->_fields[$name]->validationError;
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
        if ($this->hasField($fieldNameOrAlias) || isset($this->_aliasToLocalField[$fieldNameOrAlias])) {
            if (count($args)) {
                $this->$fieldNameOrAlias = array_shift($args);
                return $this;
            } else {
                return $this->$fieldNameOrAlias;
            }
        } else if (
            preg_match('%^(.*)_restore$%i', $fieldNameOrAlias, $fieldParts)
            && in_array($fieldParts[1], $this->fileFields)
            && count($args) == 1
        ) {
            return $this->exists() ? $this->_fields[$fieldParts[1]]->restoreImageVersionByFileName($args[0]) : false;
        } else {
            throw new DbObjectException($this, "Unknown method: [$fieldNameOrAlias]");
        }
    }

    /**
     * Set db field value
     * @param string $fieldNameOrAlias
     * @param mixed $value
     * @throws DbObjectException
     */
    public function __set($fieldNameOrAlias, $value) {
        if (array_key_exists($fieldNameOrAlias, $this->model->fields)) {
            $this->_fields[$fieldNameOrAlias]->value = $value;
        } else if (isset($this->_aliasToLocalField[$fieldNameOrAlias])) {
            $this->initRelatedObject($fieldNameOrAlias, $value);
        } else if ($this->hasRelationField($fieldNameOrAlias)) {
            list($relationAlias, $fieldName) = $this->parseRelationField($fieldNameOrAlias);
            $this->$relationAlias->$fieldName = $value;
        } else {
            throw new DbObjectException($this, "Unknown DB field: [$fieldNameOrAlias]");
        }
    }

    /**
     * Get value of db field
     * @param string $fieldNameOrAlias
     * @return mixed
     * @throws DbObjectException
     */
    public function __get($fieldNameOrAlias) {
        if (array_key_exists($fieldNameOrAlias, $this->model->fields)) {
            return $this->_fields[$fieldNameOrAlias]->value;
        } else if (isset($this->_aliasToLocalField[$fieldNameOrAlias])) {
            // related object
            if ($this->_relatedObjects[$fieldNameOrAlias] === false) {
                // Related Object not loaded
                $this->findRelatedObject($fieldNameOrAlias);
            }
            return $this->_relatedObjects[$fieldNameOrAlias];
        } else if ($this->hasRelationField($fieldNameOrAlias)) {
            list($relationAlias, $fieldName) = $this->parseRelationField($fieldNameOrAlias);
            return $this->$relationAlias->exists(true) ? $this->$relationAlias->$fieldName : null;
        } else if ($fieldNameOrAlias == 'validationErrors') {
            return $this->collectValidationErrors();
        } else if (
            preg_match('%^(.*)_(path|exists)$%is', $fieldNameOrAlias, $matches)
            && array_key_exists($matches[1], $this->model->fields)
        ) {
            // field name looks like "file_path" or "file_exists" and field "file" exists in object
            return $this->_fields[$matches[1]]->{'_file_' . $matches[2]};
        } else if (
            preg_match('%^(.*)(_date|_time|_ts)$%is', $fieldNameOrAlias, $matches)
            && array_key_exists($matches[1], $this->model->fields)
        ) {
            return $this->_fields[$matches[1]]->$matches[2];
        } else {
            throw new DbObjectException($this, "Unknown DB field: [$fieldNameOrAlias]");
        }

    }

    protected function collectValidationErrors() {
        $errors = array();
        foreach ($this->model->fields as $fieldNameOrAlias => $null) {
            if (!empty($this->_fields[$fieldNameOrAlias]->validationError)) {
                $errors[$fieldNameOrAlias] = $this->_fields[$fieldNameOrAlias]->validationError;
            }
        }
        // add custom errors
        if (!empty($this->customErrors)) {
            if (empty($errors)) {
                $errors = $this->customErrors;
            } else if (is_array($this->customErrors)) {
                $errors = array_merge($errors, $this->customErrors);
            } else {
                $errors[] = $this->customErrors;
            }
        }
        foreach ($this->_relatedObjects as $alias => $object) {
            if (is_object($object)) {
                if (!empty($object->validationErrors)) {
                    $errors[$alias] = $object->validationErrors;
                }
            } else if (is_array($object)) {
                foreach ($object as $index => $realObject) {
                    if (!empty($realObject->validationErrors)) {
                        if (empty($errors[$alias])) {
                            $errors[$alias] = array();
                        }
                        $errors[$alias][$index] = $realObject->validationErrors;
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
        if (array_key_exists($fieldNameOrAlias, $this->_fields)) {
            unset($this->_fields[$fieldNameOrAlias]->value);
            // unset linked relations
            foreach ($this->_fields[$fieldNameOrAlias]->getRelations() as $relationAlias) {
                $this->cleanRelatedObject($relationAlias);
            }
        } else if (isset($this->_aliasToLocalField[$fieldNameOrAlias])) {
            $this->cleanRelatedObject($fieldNameOrAlias);
        }
    }

    /**
     * Check if field value is set
     * @param string $fieldNameOrAlias - field name or related object alias
     * @return bool
     */
    public function __isset($fieldNameOrAlias) {
        if (array_key_exists($fieldNameOrAlias, $this->_fields)) {
            // field
            return isset($this->_fields[$fieldNameOrAlias]->value);
        } else if (isset($this->_aliasToLocalField[$fieldNameOrAlias])) {
            // related object
            if (is_object($this->_relatedObjects[$fieldNameOrAlias])) {
                // single object
                $localField = $this->_aliasToLocalField[$fieldNameOrAlias];
                $foreignField = $this->_aliasToRelationField[$fieldNameOrAlias];
                return $this->_relatedObjects[$fieldNameOrAlias]->exists()
                    || (
                        !empty($this->$localField)
                        && !empty($this->_relatedObjects[$fieldNameOrAlias]->$foreignField)
                        && $this->$localField === $this->_relatedObjects[$fieldNameOrAlias]->$foreignField
                    );
            } else {
                // array of objects (false = not set, empty array = no objects attached)
                return $this->_relatedObjects[$fieldNameOrAlias] !== false;
            }
        } else if ($fieldNameOrAlias == 'validationErrors') {
            return true;
        }
        return false;
    }

    /**
     * Check if object has field called $fieldName
     * @param string $fieldName
     * @return bool
     */
    public function hasField($fieldName) {
        return array_key_exists($fieldName, $this->_fields) || $this->hasRelationField($fieldName);
    }

    /**
     * @param string $fieldName - can be "fieldName" or "RelationAlias.fieldName" or "Relation1Alias.Relation2Alias.fieldName"
     * @param null|string $relationAlias
     * @return bool
     * @throws DbObjectException
     */
    public function hasRelationField($fieldName, $relationAlias = null) {
        if (empty($relationAlias)) {
            list($relationAlias, $fieldName) = $this->parseRelationField($fieldName);
            if ((empty($relationAlias))) {
                return false;
            }
        }
        if (empty($this->model->relations[$relationAlias])) {
            throw new DbObjectException($this, "Unknown relation with alias [$relationAlias]");
        }
        return ($this->$relationAlias->hasField($fieldName));
    }

    /**
     * Split $fieldNameAndRelation onto $relation and $fieldName
     * @param string $fieldNameAndRelation
     * @return array - array($relation, $fieldName), relation can be null if $fieldNameAndRelation does not contain it
     */
    protected function parseRelationField($fieldNameAndRelation) {
        $exploded = explode('.', $fieldNameAndRelation, 2);
        if (count($exploded) == 2) {
            return $exploded;
        } else {
            return array(null, $fieldNameAndRelation);
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
        $this->updatedFields = array();
        $this->storeFieldUpdates = true;
        $this->dataBackup = $this->getFieldsValues();
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
        if (!empty($this->updatedFields)) {
            $ret = $this->saveUpdates($this->updatedFields);
        }
        if ($ret) {
            $this->updatedFields = array();
            $this->storeFieldUpdates = false;
            $this->dataBackup = null;
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
        $this->storeFieldUpdates = false;
        foreach ($this->updatedFields as $fieldName) {
            if (isset($this->_fields[$fieldName])) {
                if (array_key_exists($fieldName, $this->dataBackup)) {
                    $this->_fields[$fieldName]->value = $this->dataBackup[$fieldName];
                } else {
                    unset($this->_fields[$fieldName]->value);
                }
            }
        }
        $this->updatedFields = array();
        $this->dataBackup = null;
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
                if (!in_array($relations, array_keys($this->_aliasToClassName))) {
                    throw new DbObjectException($this, "Unknown relation [{$relations}] in [{$this->model->getAlias()}]");
                }
                $relations = array($relations);
            } else if (is_array($relations)) {
                $diff = array();
                foreach ($relations as $alias) {
                    if (!array_key_exists($alias, $this->_aliasToClassName)) {
                        $diff[] = $alias;
                    }
                }
                if (!empty($diff)) {
                    $unknown = '[' . implode('], [', $diff) . ']';
                    throw new DbObjectException($this, "Unknown relations $unknown in [{$this->model->getAlias()}]");
                }
            } else if ($action == 'begin') {
                $relations = array_keys($this->_aliasToClassName);
            } else if (in_array($action, array('commit', 'rollback'))) {
                if (empty($this->_relationsBeginned)) {
                    return true; //< nothing to commit or roll back
                } else {
                    $relations = $this->_relationsBeginned;
                }
                $cleanAll = true;
            } else {
                // all relations
                $relations = array_keys($this->_aliasToClassName);
            }
            if ($action == 'begin') {
                $this->_relationsBeginned = $relations;
            }
            $success = true;
            // perform action on all collected relations
            foreach ($relations as $alias) {
                if (empty($this->_relatedObjects[$alias])) {
                    continue;
                }
                $localField = $this->_aliasToLocalField[$alias];
                if (!isset($localField)) {
                    throw new DbObjectException($this, "Unknown relation with alias [$alias]");
                } else if (empty($this->$localField)) {
                    throw new DbObjectException($this, "Cannot link [{$this->model->getAlias()}] with [{$alias}]: [{$this->model->getAlias()}->{$localField}] is empty");
                }
                $relationField = $this->_aliasToRelationField[$alias];
                if (is_array($this->_relatedObjects[$alias])) {
                    /** @var DbObject $object */
                    foreach ($this->_relatedObjects[$alias] as $object) {
                        if ($isSaveAction && $relationField != $object->model->getPkColumn()) {
                            $object->$relationField($this->$localField);
                        }
                        $ret = $object->$action();
                        if ($isSaveAction && !$ret) {
                            $success = false;
                        }
                    }
                } else if (is_object($this->_relatedObjects[$alias])) {
                    if ($isSaveAction) {
                        if (
                            !empty($this->_relatedObjects[$alias]->$relationField)
                            && $this->_relatedObjects[$alias]->$relationField !== $this->$localField
                        ) {
                            throw new DbObjectException($this, "Trying to attach [$alias] that already attached to another [{$this->model->getAlias()}]");
                        }
                        $this->_relatedObjects[$alias]->$relationField($this->$localField);
                    }
                    if ($action === 'save') {
                        $ret = $this->_relatedObjects[$alias]->save(true, true);
                    } else {
                        $ret = $this->_relatedObjects[$alias]->$action();
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
            if (!in_array($relations, array_keys($this->_aliasToClassName))) {
                throw new DbObjectException($this, "Unknown relation [{$relations}] in [{$this->model->getAlias()}]");
            }
            $relations = array($relations);
        } else if (is_array($relations)) {
            $diff = array(); array_intersect($relations, array_keys($this->_aliasToClassName));
            foreach ($relations as $alias) {
                if (!array_key_exists($alias, $this->_aliasToClassName)) {
                    $diff[] = $alias;
                }
            }
            if (!empty($diff)) {
                $unknown = '[' . implode('], [', $diff) . ']';
                throw new DbObjectException($this, "Unknown relations $unknown in [{$this->model->getAlias()}]");
            }
        }
        $relatedObjects = array();
        foreach ($relations as $alias) {
            if (empty($this->_relatedObjects[$alias])) {
                continue;
            }
            $relatedObjects[$alias] = $this->_relatedObjects[$alias];
            $this->cleanRelatedObject($alias);
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
        if ($this->allowFieldsUpdatesTracking) {
            if ($this->storeFieldUpdates) {
                $this->updatedFields[] = $fieldName;
            } else {
                $this->updatedFields = array($fieldName); //< for single field saving
            }
        }
        // check if field linked with related objects
        if (in_array($fieldName, $this->_aliasToLocalField)) {
            // reinit related objects
            foreach ($this->_fields[$fieldName]->getRelations() as $alias) {
                if (!empty($this->_relatedObjects[$alias])) {
                    $this->initRelatedObject($alias);
                }
            }
        }
        return $this;
    }

    /**
     * Returns primary key value
     * Note: uses $this->exists(false)
     * @return mixed|null
     */
    public function pkValue() {
        return ($this->exists()) ? $this->_fields[$this->model->getPkColumn()]->value : null;
    }

    /**
     * Read data from DB using Model
     * @param int|string $pkValue - primary key value
     * @param array|string $fields - list of fields to get
     * @param array|string|null|bool $relations - related objects to read
     * @return $this
     */
    public function read($pkValue, $fields = '*', $relations = false) {
        $this->{$this->model->getPkColumn()} = $pkValue;
        return $this->find($this->getFindByPkConditions(), $fields, $relations);
    }

    /**
     * Reload data from DB using stored pk value
     * @param array|string $fields - list of fields to get
     * @param array|string|null|bool $relations - related objects to read. null: reload currently loaded related objects
     * @return $this
     * @throws DbObjectException
     */
    public function reload($fields = '*', $relations = null) {
        if (!empty($this->{$this->model->getPkColumn()})) {
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
            return $this->find($this->getFindByPkConditions(), $fields, $relations);
        } else {
            throw new DbObjectException($this, 'Cannot load object if primary key is empty');
        }
    }

    /**
     * Read required fields from DB and update current object
     * Note: does not work with not existing object
     * @param string|array $fields
     * @return $this
     */
    public function readFields($fields = '*') {
        if ($this->exists()) {
            return $this->find($this->getFindByPkConditions(), $fields);
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
        $this->cleanRelatedObjects();
        if ($relations !== false) {
            if (empty($relations) || $relations === true) {
                $relations = array_keys($this->_aliasToLocalField);
            } else if (is_string($relations)) {
                $relations = array($relations);
            }
            foreach ($relations as $alias) {
                $this->$alias; //< calling get will read related object
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
        $localField = $this->_aliasToLocalField[$relationAlias];
        if (empty($this->$localField)) {
            // local field empty - bad situation but possible when creating new record together with all related
            if (!$ignorePkNotSetError || ($localField != $this->model->getPkColumn() && $this->_fields[$localField]->required)) {
                throw new DbObjectException($this, "Cannot validate relation between [{$this->model->getAlias()}] and [{$relationAlias}]: [{$this->model->getAlias()}->{$localField}] is empty");
            }
        } else {
            $relationType = $this->_aliasToRelationType[$relationAlias];
            $foreignField = $this->_aliasToRelationField[$relationAlias];
            if (in_array($relationType, array('one', 'belong'))) {
                $objects = array($this->_relatedObjects[$relationAlias]);
            } else {
                $objects = $this->_relatedObjects[$relationAlias];
            }
            // if any related object is invalid - whole set is invalid
            foreach ($objects as $relatedObject) {
                if (
                    empty($relatedObject->$foreignField)
                    || $relatedObject->$foreignField != $this->$localField
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
    protected function findRelatedObject($relationAlias) {
        $this->_relatedObjects[$relationAlias] = array();
        $relationType = $this->_aliasToRelationType[$relationAlias];
        $localField = $this->_aliasToLocalField[$relationAlias];
        if (empty($this->$localField) && !$this->_fields[$localField]->null) {
            // local field empty - bad situation
            throw new DbObjectException($this, "Cannot find related object [{$relationAlias}] [{$this->model->getAlias()}->{$localField}] is empty");
        } else if (empty($this->$localField)) {
            return null;
        } else {
            // load object[s]
            $conditions = $this->insertDataIntoRelationConditions($this->_aliasToJoinConditions[$relationAlias]);
            if (in_array($relationType, array('one', 'belong'))) {
                /** @var DbObject $relatedObject */
                $relatedObject = $this->initRelatedObject($relationAlias);
                // change model alias for some time
                $modelAliasBak = $relatedObject->model->getAlias();
                $relatedObject->model->setAlias($relationAlias);
                $relatedObject->find($conditions)->linkTo($this);
                $relatedObject->model->setAlias($modelAliasBak); //< restore model alias to default value
            } else {
                $model = $this->model->getRelatedModel($relationAlias);
                // change model alias for some time
                $modelAliasBak = $model->getAlias();
                $model->setAlias($relationAlias);
                $this->_relatedObjects[$relationAlias] = $model->select('*', $conditions, true);
                $model->setAlias($modelAliasBak); //< restore model alias to default value
            }
            return $this->_relatedObjects[$relationAlias];
        }
    }

    /**
     * Links current object with passed on
     * @param DbObject $dbObject
     * @return $this
     */
    public function linkTo(DbObject $dbObject) {
        $alias = $dbObject->model->getAlias();
        if (!empty($this->model->relations[$alias]) && $this->model->relations[$alias] !== DbRelationConfig::HAS_MANY) {
            $this->$alias = $dbObject;
        }
        return $this;
    }

    /**
     * Read data from DB using Model
     * @param string|array $conditions - conditions to use
     * @param array|string $fields - list of fields to get
     * @param array|null|string $relations - list of relations to read with object
     * @return $this
     */
    public function find($conditions, $fields = '*', $relations = array()) {
        if (is_array($fields) && !in_array($this->model->getPkColumn(), $fields)) {
            $fields[] = $this->model->getPkColumn();
        } else if (!is_array($fields) && $fields !== '*') {
            $fields = array($this->model->getPkColumn(), $fields);
        }
        $data = $this->model->getOne($fields, $conditions, false, false);
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
        foreach ($this->_fields as $object) {
            if (isset($object->value)) {
                $object->isDbValue = true;
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
        if (!$this->model->inTransaction()) {
            $this->model->begin();
            $localTransaction = true;
        }
        $exists = $this->exists($verifyDbExistance);
        if ($verifyDbExistance && !$exists && !$createIfNotExists) {
            $this->customErrors[$this->model->getPkColumn()] = '@!db.error_edit_not_existing_record@';
            if ($localTransaction) {
                $this->model->rollback();
            }
            return false;
        }
        $relatedObjects = !empty($saveRelations)
            ? $this->getRelationsToSave($saveRelations)
            : array();
        if (!$exists) {
            $this->allowFieldsUpdatesTracking = false;
            $ret = $this->model->insert($this->toStrictArray(), '*');
            if (!empty($ret)) {
                $this->updateWithDbData($ret);
            }
            $this->allowFieldsUpdatesTracking = true;
        } else {
            $dataToSave = $this->toStrictArray(null, true);
            unset($dataToSave[$this->model->getPkColumn()]);
            if (!empty($dataToSave)) {
                $this->allowFieldsUpdatesTracking = false;
                $ret = $this->model->update($dataToSave, $this->getFindByPkConditions(), '*');
                if (!empty($ret) && count($ret) == 1) {
                    $ret = $ret[0];
                    $this->updateWithDbData($ret);
                } else if (count($ret) > 1) {
                    $this->model->rollback();
                    throw new DbObjectException($this, 'Attempt to update [' . count($ret) . '] records instead of 1: ' . $this->model->lastQuery());
                }
                $this->allowFieldsUpdatesTracking = true;
            } else {
                // nothing to update
                $ret = true;
            }
        }
        if (!empty($ret)) {
            // save attached files
            if ($this->hasFiles) {
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
                $this->model->commit();
            } else {
                $this->model->rollback();
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
     * @param null|string|array $fields
     * @return bool
     */
    protected function saveFiles($fields = null) {
        if (!$this->exists()) {
            return false;
        }
        if (empty($fields) || !is_array($fields)) {
            $fields = $this->fileFields;
        } else {
            $fields = array_intersect($this->fileFields, $fields);
        }
        foreach ($fields as $fieldName) {
            if ($this->_fields[$fieldName]->isFile && $this->_fields[$fieldName]->isUploadedFile()) {
                $this->saveFile($fieldName, $this->_fields[$fieldName]->value);
            }
        }
        return true;
    }

    /**
     * Save single field value to db
     * @param string $name
     * @param mixed $value
     * @return bool
     * @throws DbObjectException when field does not belong to db object
     */
    public function saveField($name, $value) {
        if (isset($this->_fields[$name])) {
            return $this->begin()->$name($value)->commit();
        }
        $class = get_class($this);
        throw new DbObjectException($this, "Unknown field [$name] detected in [$class]");
    }

    /**
     * Save updates for certain fields of existing objects
     * Note: does not work with relations
     * @params format: saveUpdates(array) | saveUpdates(field1 [,field2])
     * @param array|string|null $fields
     * @param string $field2
     * @param string $field3
     * @return bool
     */
    public function saveUpdates($fields = null, $field2 = null, $field3 = null) {
        if ($this->exists()) {
            $localTransaction = false;
            $fields = func_get_args();
            if (empty($fields)) {
                $fields = null;
            } else if (is_array($fields[0])) {
                $fields = $fields[0];
            }
            $errors = $this->validate($fields, true);
            if (!empty($errors)) {
                return false;
            }
            if (!$this->model->inTransaction()) {
                $this->model->begin();
                $localTransaction = true;
            }
            $dataToSave = $this->toStrictArray($fields, true);
            unset($dataToSave[$this->model->getPkColumn()]);
            if (!empty($dataToSave)) {
                $ret = $this->model->update($dataToSave, $this->getFindByPkConditions());
            } else {
                $ret = true;
            }
            if (!empty($ret) && $this->hasFiles) {
                // save attached files
                $this->saveFiles();
                $ret = true;
            }
            if (!empty($ret)) {
                if ($localTransaction) {
                    $this->model->commit();
                }
                $this->markAllSetFieldsAsDbFields();
            } else {
                if ($localTransaction) {
                    $this->model->rollback();
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
            $alreadyInTransaction = $this->model->inTransaction();
            if (!$alreadyInTransaction) {
                $this->model->begin();
            }
            $this->model->delete($this->getFindByPkConditions());
            $this->afterDelete();
            if (!$alreadyInTransaction) {
                $this->model->commit();
            }
            if (empty($this->model->dontDeleteFiles) && $this->hasFiles) {
                $this->deleteFilesForAllFields();
            }
        }
        // note: related objects delete must be managed only by database relations (foreign keys), not here
        $this->existsInDb = -1;
        if ($resetFields) {
            $this->reset();
        } else {
            $this->_fields[$this->model->getPkColumn()]->reset();
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
        foreach ($this->fileFields as $fieldName) {
            if ($this->_fields[$fieldName]->isFile) {
                Folder::load($this->buildPathToFiles($fieldName))->delete();
            }
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
        $pkSet = !empty($this->_fields[$this->model->getPkColumn()]->value);
        if ($fromDb) {
            return $pkSet && $this->model->exists($this->getFindByPkConditions());
        } else {
            return $pkSet;
        }
    }

    /**
     * collect conditions for search by primary key query
     * @return array
     */
    protected function getFindByPkConditions() {
        return array($this->model->getPkColumn() => $this->pkValue());
    }

    /**
     * Collect all values into associative array (all field values are validated)
     * Used to collect values to write them to db
     * @param array|null $fields - will return only this fields (if not skipped).
     *      Note: pk field added automatically if object has it
     * @param bool $onlyUpdated - true: will return only field values that were updated (field->isDbValue == false)
     * @return array
     * @throws DbObjectException when some field value validation fails
     */
    public function toStrictArray($fields = null, $onlyUpdated = false) {
        $values = array();
        if (empty($fields) || !is_array($fields)) {
            $fields = array_keys($this->_fields);
        }
        if ($this->exists() && !in_array($this->model->getPkColumn(), $fields)) {
            $fields[] = $this->model->getPkColumn();
        }
        foreach ($fields as $name) {
            if (!isset($this->_fields[$name])) {
                throw new DbObjectException($this, "Field [$name] not exists in table [{$this->model->getTableName()}]");
            } else if (!$this->_fields[$name]->skip($onlyUpdated)) {
                $values[$name] = $this->_fields[$name]->value;
            }
        }
        return $values;
    }

    /**
     * Collect available values into associative array (does not validate field values)
     * Used to just get values from object. Also can be overwritten in child classes to add/remove specific values
     * @param array|null $fields - will return only this fields (if not skipped)
     *      Note: pk field added automatically if object has it
     * @param array|string|bool $relations - array and string: relations to return | true: all relations | false: without relations
     * @param bool $forceRelationsRead - true: relations will be read before processing | false: only previously read relations will be returned
     * @return array
     */
    public function toPublicArray($fields = null, $relations = false, $forceRelationsRead = true) {
        $values = array();
        if (empty($fields) || !is_array($fields)) {
            $fields = array_keys($this->_fields);
        }
        if ($this->exists() && !in_array($this->model->getPkColumn(), $fields)) {
            $fields[] = $this->model->getPkColumn();
        }
        foreach ($fields as $name) {
            if (isset($this->_fields[$name]->value)) {
                $values[$name] = $this->_fields[$name]->value;
            } else if ($this->exists() && $this->_fields[$name]->isFile) {
                $this->_fields[$name]->value = $this->{$this->model->getPkColumn()}; //< this will trigger file path generation
                $values[$name] = $this->_fields[$name]->value;
                /*$server = !empty($this->model->fields[$name]['server']) ? $this->model->fields[$name]['server'] : null;
                if ($this->_fields[$name]->isImage && $server == \Server::alias()) {
                    $values[$name . '_path'] = $this->{$name . '_path'};
                }*/
                if ($this->_fields[$name]->isImage) {
                    $values[$name . '_path'] = $this->{$name . '_path'};
                }
            }
        }

        return $values + $this->relationsToPublicArray($relations, $forceRelationsRead);
    }

    public function relationsToPublicArray($relations = null, $forceRelationsRead = true) {
        if (empty($relations)) {
            return array();
        } if ($relations === true) {
            $relations = array_keys($this->_aliasToLocalField);
        } else if (is_string($relations)) {
            $relations = array($relations);
        } else if (!is_array($relations)) {
            throw new DbObjectException($this, 'DbObject->relationsToPublicArray: Invalid $relations (not a string/bool/array)');
        }
        $return = array();
        foreach ($relations as $alias => $fields) {
            if (is_numeric($alias)) {
                $alias = $fields;
                $fields = null;
            }
            if (!isset($this->_aliasToLocalField[$alias])) {
                throw new DbObjectException($this, "Unknown relation with alias [$alias]");
            }
            $return[$alias] = array();
            if ($forceRelationsRead) {
                $this->$alias; //< read relation data
            }
            // show related object if it is set or
            if (!empty($this->_relatedObjects[$alias])) {
                if (is_array($this->_relatedObjects[$alias])) {
                    /** @var DbObject $object */
                    foreach ($this->_relatedObjects[$alias] as $object) {
                        $return[$alias][] = $object->toPublicArray($fields);
                    }
                } else {
                    $return[$alias] = $this->_relatedObjects[$alias]->toPublicArray($fields);
                }
            }
        }
        return $return;
    }

    /**
     * Collect default values for the fields
     * @param array|null $fields - will return only this fields (if not skipped)
     * @return array
     */
    public function getDefaultsArray($fields = null) {
        $values = array();
        if (empty($fields) || !is_array($fields)) {
            $fields = array_keys($this->_fields);
        }
        foreach ($fields as $name) {
            $values[$name] = $this->_fields[$name]->default;
        }
        return $values;
    }

    static public function isUploadedFile($fileInfo) {
        return array_key_exists('tmp_name', $fileInfo) && empty($fileInfo['error']) && !empty($fileInfo['size']);
    }

    /**
     * Collect values of all fields avoiding exceptions
     * Fields that were not set will be ignored
     * @return array
     */
    public function getFieldsValues() {
        $values = array();
        foreach ($this->_fields as $name => $fieldObject) {
            if (isset($fieldObject->value)) {
                $values[$name] = $fieldObject->value;
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
        return $this->pkValue();
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
     * @param string $field
     * @return string
     */
    public function getFilesBaseRelativeUrl($field) {
        $subdir = !empty($this->model->fields[$field]['subdir']) ? trim($this->model->fields[$field]['subdir'], '/\\') . '/' : '';
        $objectSubdir = $this->getFilesSubdir('/');
        if (!empty($objectSubdir)) {
            $objectSubdir = '/' . trim($objectSubdir, '/\\') . '/';
        }
        return '/' . trim($this->model->fields[$field]['base_url'], '/\\') . $objectSubdir . $subdir;
    }

    /**
     * Get absolute url to files by $field
     * @param $field
     * @return string
     */
    public function getFilesAbsoluteUrl($field) {
        return /*rtrim($this->getFilesServer($field), '/') . */$this->getFilesBaseRelativeUrl($field);
    }

    /**
     * Get base file name for $field (without suffix and extension)
     * @param string $field
     * @return string
     */
    public function getBaseFileName($field) {
        return isset($this->model->fields[$field]) && isset($this->model->fields[$field]['filename'])
            ? $this->model->fields[$field]['filename']
            : $field;
    }

    /**
     * Get full file name for $field (with suffix and extension)
     * @param string $field
     * @param string $suffix
     * @return string
     */
    public function getFullFileName($field, $suffix = '') {
        $baseName = $this->getBaseFileName($field) . $suffix;
        $pathTofiles = $this->buildPathToFiles($field);
        if (!empty($this->model->fields[$field]['extension'])) {
            if (is_array($this->model->fields[$field]['extension'])) {
                foreach ($this->model->fields[$field]['extension'] as $ext) {
                    if (File::exist($pathTofiles . $baseName . '.' . $ext)) {
                        $baseName .= '.' . $ext;
                        break;
                    }
                }
            } else {
                $baseName .= '.' . $this->model->fields[$field]['extension'];
            }
        } else if (File::exist($pathTofiles . $baseName . '.ext')) {
            $baseName .= File::contents();
        }
        return $baseName;
    }

    /**
     * Build FS path to files (absolute FS path to folder with files)
     * @param string $field
     * @return string
     */
    public function buildPathToFiles($field) {
        if (!empty($field) && $this->exists() && isset($this->model->fields[$field]) && isset($this->model->fields[$field]['dir'])) {
            $objectSubdir = $this->getFilesSubdir(DIRECTORY_SEPARATOR);
            if (!empty($objectSubdir)) {
                $objectSubdir = DIRECTORY_SEPARATOR . trim($objectSubdir, '/\\') . DIRECTORY_SEPARATOR;
            }
            $subdir = !empty($this->model->fields[$field]['subdir']) ? trim($this->model->fields[$field]['subdir'], '/\\') . DIRECTORY_SEPARATOR : '';
            return rtrim($this->model->fields[$field]['dir'], '/\\') . $objectSubdir . $subdir;
        }
        return 'undefined.file';
    }

    /**
     * Build base url to files (url to folder with files)
     * @param string $field
     * @return string
     */
    public function buildBaseUrlToFiles($field) {
        if (!empty($field) && $this->exists() && isset($this->model->fields[$field]) && isset($this->model->fields[$field]['base_url'])) {
            return $this->getFilesAbsoluteUrl($field);
        }
        return 'undefined.file';
    }

    /**
     * Get urls to images
     * @param string $field
     * @return array
     */
    public function getImagesUrl($field) {
        $images = array();
        if (!empty($field) && $this->exists() && isset($this->model->fields[$field])) {
            $images = ImageUtils::getVersionsUrls(
                $this->buildPathToFiles($field),
                $this->buildBaseUrlToFiles($field),
                $this->getBaseFileName($field),
                isset($this->model->fields[$field]['resize_settings']) ? $this->model->fields[$field]['resize_settings'] : array()
            );
        }
        return $images;
    }

    /**
     * Get fs paths to images
     * @param string $field
     * @return array
     */
    public function getImagesPaths($field) {
        $images = array();
        if (!empty($field) && $this->exists() && isset($this->model->fields[$field])) {
            $images = ImageUtils::getVersionsPaths(
                $this->buildPathToFiles($field),
                $this->getBaseFileName($field),
                isset($this->model->fields[$field]['resize_settings']) ? $this->model->fields[$field]['resize_settings'] : array()
            );
        }
        return $images;
    }

    /**
     * Get fs path to file
     * @param string $field
     * @return string
     */
    public function getFilePath($field) {
        return $this->buildPathToFiles($field) . $this->getFullFileName($field);
    }

    /**
     * Get url to file
     * @param string $field
     * @return string
     */
    public function getFileUrl($field) {
        $ret = $this->buildBaseUrlToFiles($field) . $this->getFullFileName($field);
        return $ret;
    }

    protected function canSaveFile($field, $fileInfo) {
        return !empty($fileInfo)
            && $this->exists(true)
            && isset($this->model->fields[$field])
            && in_array($this->model->fields[$field]['type'], DbColumnConfig::$fileTypes)
            && self::isUploadedFile($fileInfo);
    }

    /**
     * Save file for field using field settings ($this->fields[$field])
     * If field type is image - will create required image resizes
     * @param string $field
     * @param array $fileInfo - uploaded file info
     * @return bool|string - string: path to uploaded file (not image)
     */
    public function saveFile($field, $fileInfo) {
        if ($this->canSaveFile($field, $fileInfo)) {
            if (!defined('UNLIMITED_EXECUTION') || !UNLIMITED_EXECUTION) {
                set_time_limit(90);
                ini_set('memory_limit', '128M');
            }
            $baseFileName = $this->getBaseFileName($field);
            if (in_array($this->model->fields[$field]['type'], DbColumnConfig::$imageFileTypes)) {
                $pathToFiles = $this->buildPathToFiles($field);
                // save image and crate resizes for it
                $resizeSettings = empty($this->model->fields[$field]['resize_settings'])
                    ? array()
                    : $this->model->fields[$field]['resize_settings'];
                return ImageUtils::resize($fileInfo, $pathToFiles, $baseFileName, $resizeSettings);
            } else {
                // save file
               return $this->saveFileWithCustomName($field, $fileInfo);
            }
        }
        return false;
    }

    /**
     * Save file for field using field settings ($this->fields[$field]) and provided file suffix
     * Note: will not create image resizes
     * @param string $field
     * @param array $fileInfo - uploaded file info
     * @param string $fileSuffix - custom file name
     * @return bool|string - string: path to uploaded file
     */
    public function saveFileWithCustomName($field, $fileInfo, $fileSuffix = '') {
        if ($this->canSaveFile($field, $fileInfo)) {
            $pathToFiles = $this->buildPathToFiles($field);
            if (!is_dir($pathToFiles)) {
                Folder::add($pathToFiles, 0777);
            }
            $filePath = $pathToFiles . $this->getBaseFileName($field) . $fileSuffix;
            $ext = $this->detectUploadedFileExtension($fileInfo, $this->model->fields[$field]);
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
     * Dettect Uploaded file extension by file name or content type
     * @param array $fileInfo - uploaded file info
     * @param array $fieldInfo - file field info (may contain 'extension' key to limit possible extensions)
     * @param string|bool $saveExtToFile - string: file path to save extension to.
     *      Extension saved to file only when empty($fieldInfo['extension']) and extesion detected
     * @return string|bool -
     *      string: file extension without leading point (ex: 'mp4', 'mov', '')
     *      false: invalid file info or not supported extension
     */
    protected function detectUploadedFileExtension($fileInfo, $fieldInfo, $saveExtToFile = false) {
        if (empty($fileInfo['type']) && empty($fileInfo['name'])) {
            return false;
        }
        // test content type
        $ext = false;
        if (!empty($fileInfo['type'])) {
            $ext = array_search($fileInfo['type'], self::$extToConetntType);
        }
        if (!empty($fileInfo['name']) && (empty($ext) || is_numeric($ext))) {
            $ext = preg_match('%\.([a-zA-Z0-9]+)\s*$%is', $fileInfo['name'], $matches) ? $matches[1] : '';
        }
        if (!empty($fieldInfo['extension'])) {
            if (empty($ext)) {
                $ext = is_array($fieldInfo['extension'])
                    ? array_shift($fieldInfo['extension'])
                    : $fieldInfo['extension'];
            } else if (
                (is_array($fieldInfo['extension']) && !in_array($ext, $fieldInfo['extension']))
                || (is_string($fieldInfo['extension']) && $ext != $fieldInfo['extension'])
            ) {
                return false;
            }
        } else if ($saveExtToFile && !empty($ext)) {
            File::save($saveExtToFile, $ext, 0666);
        }
        return $ext;
    }

    /**
     * Delete files attached to DbObject field
     * @param string $field
     * @param string $fileSuffix
     */
    public function deleteFilesForField($field, $fileSuffix = '') {
        if (
            isset($this->model->fields[$field])
            && in_array($this->model->fields[$field]['type'], DbColumnConfig::$fileTypes)
            && $this->exists(true)
        ) {
            $pathToFiles = $this->buildPathToFiles($field);
            if (is_dir($pathToFiles)) {
                $files = scandir($pathToFiles);
                $baseFileName = $this->getBaseFileName($field);
                foreach ($files as $fileName) {
                    if (preg_match("%^{$baseFileName}{$fileSuffix}%is", $fileName)) {
                        @File::remove(rtrim($pathToFiles, '/\\') . DIRECTORY_SEPARATOR . $fileName);
                    }
                }
            }
        }
    }

}