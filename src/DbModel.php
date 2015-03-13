<?php

namespace PeskyORM;
use ORM\DbTableConfig;
use PeskyORM\Exception\DbModelException;
use PeskyORM\Exception\DbQueryException;
use PeskyORM\Exception\DbUtilsException;
use PeskyORM\Lib\Folder;
use PeskyORM\Lib\File;
use PeskyORM\Lib\ImageUtils;
use PeskyORM\Lib\Set;
use PeskyORM\Lib\StringUtils;

/**
 * Class Model
 */
abstract class DbModel {

    /** @var null|Db */
    static protected $dataSource = array();
    static protected $dbConfigs = array();
    static public $loadedModels = array();    //< Model objects

    /** @var DbTableConfig */
    protected $tableConfig;
    public $alias = null;
    public $orderField = null;
    public $orderDirection = 'asc';

    /**
     * Fields description in format:
        'name' => array(
            'type' => \Db\DbObjectField::TYPE_*,
            'default' => null,
            'null' => true,
            'required' => \Db\DbObjectField::ON_NONE,
            'length' => 0,
            'validators' => array(
                \Db\DbObjectField::VALIDATOR_*,
                \Db\DbObjectField::VALIDATOR_* => array(),
                \Db\DbObjectField::VALIDATOR_* => 1,
            ),
            'view_settings' => array(
                'index' => array(...)
                'view' => array(...)
                'form' => array(...)
            )
        )
     * you can avoid info in order to use DbObject::$idFieldInfo or DbObject::$fkFieldInfo as field info - just write field name as array value
     * @var array
     */
    public $fields = array();
    /**
     * In Safe Mode Db Objects will throw exceptions when they receive unknown field
     * @var bool
     */
    public $safeMode = true;
    /**
     * list of foreign keys of a Model with configs
     * Format for belongs to relation
     * $foreignKeys = array(
     *      'alias' => array(
     *          'many' => bool,              //< false: belongsTo or hasOne relation | true: hasMany relation
     *          'model' => string,
     *          'local_field' => string,    //< field name of current model
     *          'foreign_field' => string,  //< field name of other model
     *          'display_field' => string,  //< this field will replace foreign_field when item is displayed
     *          'conditions' => array,      //< additional join conditions
     *          'controller' => string,     //< with action - used to build an URL to item
     *          'action' => string
     *      )
     *
     * @var array
     */
    public $relations = array();
    public $validationErrors = array();

    public $dontDeleteFiles = false; //< true: do not delete attached files in DbObject->delete()

    const TYPE_STRING = 1;
    const TYPE_TEXT = 2;
    const TYPE_BOOL = 3;
    const TYPE_INT = 4;
    const TYPE_FLOAT = 5;

    const ERROR_EMPTY = '@!data_error.field_is_empty@';
    const ERROR_TYPE_MISSMATCH = '@!data_error.type_missmatch@';

    public function __construct() {
//        $this->loadConfigFile();
        if (empty($this->tableConfig->getName())) {
            throw new DbModelException($this, 'Model ' . get_class($this) . ' has no table name');
        }
        if (empty($this->tableConfig->getPk())) {
            throw new DbModelException($this, 'Model ' . get_class($this) . ' has no primary key');
        }
        if (empty($this->orderField)) {
            $this->orderField = $this->tableConfig->getPk();
        }
        if (empty($this->alias)) {
            $this->alias = StringUtils::modelize($this->getTableName());
        }
    }

    public function getTableName() {
        return $this->tableConfig->getName();
    }

    public function getPkColumn() {
        return $this->tableConfig->getPk();
    }

    /**
     * @return DbTableConfig
     */
    public function getTableConfig() {
        return $this->tableConfig;
    }

    /**
     * @param DbTableConfig $tableConfig
     * @return $this
     */
    public function setTableConfig($tableConfig) {
        $this->tableConfig = $tableConfig;
        return $this;
    }



    /**
     * Load config file and update $this->fields and $this->relations
     * Config file may contain any set of next variables:
     *      arrays: $fields, $relations,
     *      not arrays: $orderField, $orderDirection, $primaryKey, $alias, $safeMode, $dontDeleteFiles, $table
     */
    /*public function loadConfigFile() {
        foreach ($this->fields as $name => $config) {
            if (!is_array($config) && isset($this->$config) && is_array($this->$config)) {
                $this->fields[$name] = $this->$config;
            }
        }
        if ($this->configFileName && !empty($this->configsDir) && Folder::exist($this->getModelConfigsPath())) {
            $filePath = $this->getConfigFilePath();
            if (File::exist($filePath)) {
                include $filePath;
                foreach (array('orderField', 'orderDirection', 'primaryKey', 'alias', 'safeMode', 'dontDeleteFiles', 'table', 'schema') as $varName) {
                    if (isset($$varName)) {
                        $this->$varName = $$varName;
                    }
                }
                if (isset($fields)) {
                    $this->fields = array_merge($fields, $this->fields);
                }
                if (isset($relations)) {
                    $this->relations = array_merge($relations, $this->relations);
                }
            }
        }
    }*/

    /*public function saveConfigFile() {
        if (\Server::debug()) {
            throw new DbModelException($this, 'Model settings saving allowed only in debug mode');
        }
        $configFilePath = $this->getConfigFilePath();
        if (!empty($configFilePath)) {
            $varNames = array(
                'schema', 'table', 'alias', 'primaryKey', 'orderField', 'orderDirection',
                'safeMode', 'dontDeleteFiles',
                'fields', 'relations'
            );
            $data = array();
            foreach ($varNames as $varName) {
                $data[$varName] = $this->$varName;
            }
            $renderer = \Renderer::getLastCreatedRenderer();
            $configContents = $renderer->evaluate($renderer->getViewsPath(true) . 'model_config.tpl', $data);
            \File::save($configFilePath, $configContents);
            return true;
        }
        return false;
    }*/

    /**
     * Get path to models config files
     * @return string
     */
    /*public function getModelConfigsPath() {
        return self::getModelsPath() . $this->configsDir . DIRECTORY_SEPARATOR;
    }*/

    /**
     * Get path to this model's config path
     * @return bool|string
     */
    /*public function getConfigFilePath() {
        if ($this->configFileName) {
            if ($this->configFileName == true) {
                $this->configFileName = $this->dbObjectName();
            }
            return $this->getModelConfigsPath() . preg_replace('%Config$%is', '', $this->configFileName) . 'Config.php';
        }
        return false;
    }*/

    /**
     * Loads models by class name. Example: Model::User() will create object of class User (or pick existing if already exists)
     * @param $className - class name or table name (UserTokenModel, UserToken or user_tokens)
     * @param array $objectArgs - used only for DbObjects to pass data array or primary key value
     * @return DbModel|DbObject
     * @throws DbModelException
     */
    /*static public function __callStatic($className, $objectArgs = array()) {
        if (preg_match('%^(.*)Model$%s', $className, $matches)) {
            // model requested
            $className = $matches[1];
            return self::getModel($className);
        } else {
            // db object requested
            return self::getDbObject(
                $className,
                !empty($objectArgs) ? $objectArgs[0] : null,
                !empty($objectArgs) && isset($objectArgs[1]) ? $objectArgs[1] : null
            );
        }
    }*/

    /**
     * Get path to models
     * @return string
     */
    /*static public function getModelsPath() {
        return ROOT . DIRECTORY_SEPARATOR . 'Model' . DIRECTORY_SEPARATOR;
    }*/

    /**
     * Get path to db objects
     * @return string
     */
    /*static public function getDbObjectsPath() {
        return self::getModelsPath() . 'DbObject' . DS;
    }*/

    /**
     * Load and return requested Model
     * @param string $modelClassName - class name or table name (UserToken or user_tokens)
     * @param bool $isRecursion - true: indicates that this is recursion and another one is not allowed
     * @return DbModel
     * @throws DbModelException
     */
    /*static public function getModel($modelClassName, $isRecursion = false) {
        // load model if not loaded yet
        if (empty(self::$loadedModels[$modelClassName])) {
            $ns = '\Db\Model\\';
            $modelClassName = preg_replace('%Model$%is', '', $modelClassName) . 'Model';
            $modelFilePath = self::getModelsPath() . $modelClassName . '.php';
            if (!\File::exist($modelFilePath)) {
                if (!$isRecursion && preg_match('%^(.*)sModel$%i', $modelClassName, $matches)) {
                    // remove plural ending from class name and try again
                    self::$loadedModels[$modelClassName] = self::getModel($matches[1]);
                    return self::$loadedModels[$modelClassName];
                }
                throw new DbModelException($this, 'Model ' . $modelClassName . ' not exists: ' . $modelFilePath);
            }
            require_once($modelFilePath);
            $reflection = new \ReflectionClass($ns . $modelClassName);
            if (!in_array($reflection->getParentClass()->name, array('Model', 'AppModel', 'Db\Model', 'Db\Model\AppModel'))) {
                throw new DbModelException($this, 'Class \Db\Model must be parent of class ' . $ns . $modelClassName);
            }

            self::$loadedModels[$modelClassName] = $reflection->newInstance();
        }
        return self::$loadedModels[$modelClassName];
    }*/

    /**
     * Load and return requested Model
     * @param string $modelClassName - class name or table name (UserToken or user_tokens)
     * @return DbModel
     * @throws DbUtilsException
     */
    static public function getModel($modelClassName) {
        // load model if not loaded yet
        if (empty(self::$loadedModels[$modelClassName])) {
            $modelClassName = self::getModelsNamespace() . $modelClassName;
            if (!class_exists($modelClassName)) {
                throw new DbUtilsException("Class $modelClassName was not found");
            }
            self::$loadedModels[$modelClassName] = new $modelClassName();
        }
        return self::$loadedModels[$modelClassName];
    }

    /**
     * Get related model by relation alias
     * @param string $alias
     * @return DbModel
     * @throws DbModelException
     */
    public function getRelatedModel($alias) {
        if (!isset($this->relations[$alias])) {
            throw new DbModelException($this, "Unknown relation with alias [$alias]");
        } else if (!isset($this->relations[$alias]['model'])) {
            throw new DbModelException($this, "Model for relation [$alias] not provided");
        }
        return $this->getModel($this->relations[$alias]['model']);
    }

    /**
     * Convert get_class($this) to db object class name (without namespace)
     * @return string
     */
    public function dbObjectName() {
        return self::dbObjectNameByModelClassName(get_class($this));
    }

    /**
     * Convert get_class($this) to db object class name (without namespace)
     * @param string $class - class name. Must end on 'Model'
     * @return string
     */
    static public function dbObjectNameByModelClassName($class) {
        return preg_replace(array('%^.*[\\\]%is', '%Model$%', '%Model/%'), array('', '', 'Object'), $class);
    }

    /**
     * Load DbObject class and create new instance of it
     * @param string $dbObjectClass - class name or table name (UserToken or user_tokens)
     * @param null|array|string|int $data - null: do nothing | int and string: is primary key (read db) | array: object data
     * @param bool $filter - used only when $data not empty and is array
     *      true: filters $data that does not belong to this object
     *      false: $data that does not belong to this object will trigger exceptions
     * @return DbObject
     * @throws DbModelException
     */
    /*static public function getDbObject($dbObjectClass, $data = null, $filter = false) {
        $dbObjectClass = StringUtils::classify($dbObjectClass);
        $ns = '\Db\Object\\';
        if (!class_exists($ns . $dbObjectClass)) {
            $dbObjectFilePath = self::getDbObjectsPath() . $dbObjectClass . '.php';
            if (!File::exist($dbObjectFilePath)) {
                throw new DbModelException($this, 'DbObject ' . $dbObjectClass . ' not exists: ' . $dbObjectFilePath);
            }
            require_once($dbObjectFilePath);
            $reflection = new \ReflectionClass($ns . $dbObjectClass);
            if (!in_array($reflection->getParentClass()->name, array('DbObject', 'Db\DbObject', 'Db\AppDbObject'))) {
                throw new DbModelException($this, 'Class \Db\DbObject must be parent of class \Db\\' . $dbObjectClass);
            }
        }
        if (empty($reflection)) {
            $reflection = new \ReflectionClass($ns . $dbObjectClass);
        }
        return $reflection->newInstance(self::getModel($dbObjectClass), $data, $filter);
    }*/

    /**
     * Load DbObject class and create new instance of it
     * @param string $dbObjectClass - class name or table name (UserToken or user_tokens)
     * @param null|array|string|int $data - null: do nothing | int and string: is primary key (read db) | array: object data
     * @param bool $filter - used only when $data not empty and is array
     *      true: filters $data that does not belong to this object
     *      false: $data that does not belong to this object will trigger exceptions
     * @return DbObject
     * @throws DbUtilsException
     */
    static public function getDbObject($dbObjectClass, $data = null, $filter = false) {
        $dbObjectClass = self::getObjectsNamespace() . StringUtils::classify($dbObjectClass);
        if (!class_exists($dbObjectClass)) {
            throw new DbUtilsException("Class $dbObjectClass was not found");
        }
        return new $dbObjectClass($data, $filter);
    }

    /**
     * Get DbObject class with name space
     * @param string $dbObjectClass - object class, controller name, etc.
     * @return string
     */
    static public function getFullDbObjectClass($dbObjectClass) {
        $dbObjectClass = StringUtils::classify($dbObjectClass);
        return self::getObjectsNamespace() . $dbObjectClass;
    }

    static public function getModelsNamespace() {
        return preg_replace('%[a-zA-Z0-9_]+$%', '', get_called_class());
    }

    static public function getObjectsNamespace() {
        return preg_replace('%[a-zA-Z0-9_]+\\\$%', 'Object\\', self::getModelsNamespace());
    }

    /**
     * Load DbObject for current model and create new instance of it
     * @param null|array|string|int $data - null: do nothing | int and string: is primary key (read db) | array: object data
     * @param bool $filter - used only when $data not empty and is array
     *      true: filters $data that does not belong to this object
     *      false: $data that does not belong to this object will trigger exceptions
     * @return DbObject
     * @throws DbModelException
     */
    static public function getOwnDbObject($data = null, $filter = false) {
        $dbObjectClass = self::dbObjectNameByModelClassName(get_called_class());
        return new $dbObjectClass($data, $filter);
//        return self::getDbObject($dbObjectClass, $data, $filter);
    }

    /**
     * Collect real DB fields excluding virtual fields like files and images
     * @return array
     */
    public function getDbFields() {
        $ret = array();
        foreach ($this->fields as $name => $info) {
            if (!in_array($info['type'], DbObjectField::$fileTypes) && empty($info['virtual'])) {
                $ret[] = $name;
            }
        }
        return $ret;
    }

    static public function setDbConfigs($configs) {
        foreach ($configs as $alias => $config) {
            self::$dbConfigs[$alias] = array_merge(array('host' => 'localhost'), $config);
        }
    }

    /**
     * Get data source object
     * @param string $alias
     * @return Db
     */
    public function getDataSource($alias = 'default') {
        if (empty(self::$dataSource[$alias])) {
            self::$dataSource[$alias] = new Db(
                self::$dbConfigs['driver'],
                self::$dbConfigs['database'],
                self::$dbConfigs['user'],
                self::$dbConfigs['password'],
                self::$dbConfigs['host']
            );
        }
        return self::$dataSource;
    }

    /**
     * Convert records to DbObjects
     * @param array $records
     * @return array
     */
    public function recordsToObjects($records) {
        if (is_array($records) && !empty($records)) {
            $objects = array();
            foreach ($records as $record) {
                $objects[] = $this->getOwnDbObject($record);
            }
            return $objects;
        }
        return $records;
    }

    /**
     * Build valid 'JOIN' settings from 'CONTAIN' table aliases
     * @param array $where
     * @return mixed $where
     */
    /*public function resolveContains($where) {
        if (is_array($where)) {
            if (!empty($where['CONTAIN']) && is_array($where['CONTAIN'])) {
                $where['JOIN'] = array();
                foreach ($where['CONTAIN'] as $alias => $fields) {
                    if (is_int($alias)) {
                        $alias = $fields;
                        $fields = !empty($relation['fields']) ? $relation['fields'] : '*';
                    }
                    if (!empty($this->relations[$alias]) && empty($this->relations[$alias]['many'])) {
                        $model = self::getModel($this->relations[$alias]['model']);
                        $relation = $this->relations[$alias];
                        $where['JOIN'][$alias] = array(
                            'type' => !empty($relation['type']) ? $relation['type'] : 'left',
                            'table1_model' => $model,
                            'table1_field' => $relation['foreign_field'],
                            'table1_alias' => $alias,
                            'table2_alias' => $this->alias,
                            'table2_field' => $relation['local_field'],
                            'conditions' => !empty($relation['conditions']) && is_array($relation['conditions']) ? $relation['conditions'] : array(),
                            'fields' => $fields
                        );
                    }
                }
                if (empty($where['JOIN'])) {
                    unset($where['JOIN']);
                }
            }
            unset($where['CONTAIN']);
        }
        return $where;
    }*/

    /**
     * Add columns into options and resolve contains
     * @param mixed $columns
     * @param mixed $options
     * @return array|mixed
     */
    protected function prepareSelect($columns, $options) {
        if (!is_array($options)) {
            if (!empty($options) && is_string($options)) {
                $options = array($options);
            } else {
                $options = array();
            }
        } else {
            //$options = $this->resolveContains($options);
        }
        if (!empty($columns)) {
            $options['FIELDS'] = $columns;
        }
        return $options;
    }

    /* Queries */

    /**
     * Create query builder
     * @param null|string $modelAlias
     * @return DbQuery
     */
    public function builder($modelAlias = null) {
        return new DbQuery($this, $modelAlias);
    }

    /**
     * @param string|array $columns
     * @param null|array|string $conditionsAndOptions
     * @param bool $asObjects - true: return DbObject | false: return array
     * @param bool $withRootAlias
     * @return array[]|DbObject[]
     */
    public function select($columns = '*', $conditionsAndOptions = null, $asObjects = false, $withRootAlias = false) {
        $records = $this->builder()
            ->fromOptions($this->prepareSelect($columns, $conditionsAndOptions))
            ->find('all', $withRootAlias);
        if ($asObjects) {
            $records = $this->recordsToObjects($records);
        }
        return $records;
    }

    /**
     * @param string|array $columns
     * @param null|array|string $conditionsAndOptions
     * @param bool $asObject - true: return DbObject | false: return array
     * @param bool $withRootAlias
     * @return array|DbObject[]
     */
    public function selectOne($columns = '*', $conditionsAndOptions = null, $asObject = false, $withRootAlias = false) {
        $record = $this->builder()
            ->fromOptions($this->prepareSelect($columns, $conditionsAndOptions))
            ->find('first', $withRootAlias);
        if ($asObject) {
            $record = $this->recordsToObjects($record);
        }
        return $record;
    }

    /**
     * Selects only 1 column
     * @param string $column
     * @param null|array|string $conditionsAndOptions
     * @return array
     */
    public function selectColumn($column, $conditionsAndOptions = null) {
        $records = $this->select(array('value' => $column), $conditionsAndOptions, false, false);
        return Set::extract('/value', $records);
    }

    /**
     * Select associative array
     * Note: does not support columns from foreign models
     * @param string $keysColumn
     * @param string $valuesColumn
     * @param null|array|string $conditionsAndOptions
     * @return array
     */
    public function selectAssoc($keysColumn, $valuesColumn, $conditionsAndOptions = null) {
        $records = $this->select(array('key' => $keysColumn, 'value' => $valuesColumn), $conditionsAndOptions, false, false);
        $res = array();
        foreach ($records as $record) {
            $res[$record['key']] = $record['value'];
        }
        return $res;
    }

    /**
     * Runs Select query with count
     * @param string $columns
     * @param null|array $conditionsAndOptions
     * @param bool $asObjects - true: return DbObject | false: return array
     * @return array - array('count' => int, 'records' => array)
     */
    public function selectWithCount($columns, $conditionsAndOptions = null, $asObjects = false) {
        $conditionsAndOptions = $this->prepareSelect($columns, $conditionsAndOptions);
        $count = $this->count($conditionsAndOptions);
        if (empty($count)) {
            return array('records' => array(), 'count' => 0);
        }
        $results = array(
            'records' => $this->select($columns, $conditionsAndOptions, false, false),
            'count' => $count
        );
        if ($asObjects) {
            $results['records'] = $this->recordsToObjects($results['records']);
        }
        return $results;
    }

    /**
     * Get 1 record from DB
     * @param string|array $columns
     * @param null|array|string|int $conditionsAndOptions -
     *      array|string: conditions,
     *      numeric|int: record's pk value, automatically converted to array($this->primaryKey => $where)
     * @param bool $asObject - true: return DbObject | false: return array
     * @param bool $withRootAlias
     * @return array|bool|DbObject
     */
    public function getOne($columns, $conditionsAndOptions = null, $asObject = true, $withRootAlias = false) {
        if (is_numeric($conditionsAndOptions) || is_int($conditionsAndOptions)) {
            $conditionsAndOptions = array($this->getPkColumn() => $conditionsAndOptions);
        }
        $record = $this->builder()
            ->fromOptions($this->prepareSelect($columns, $conditionsAndOptions))
            ->findOne($withRootAlias);
        if (!is_array($record)) {
            return $record;
        } else if ($asObject) {
            return self::getOwnDbObject($record);
        } else {
            return $record;
        }
    }

    /**
     * insert single records to db
     * @param array $data
     * @param null|bool|string|array $returning
     *      string: something compatible with RETURNING for postgresql query ('*' = all fields)
     *      array: list of fields to return
     *      null: return pk value
     *      true: return all fields ('*')
     *      false: return nothing
     * @return bool|int|string|array - false: failed to insert record | string and int: primary key value of just inserted value
     */
    public function insert($data, $returning = null) {
        return $this->builder()->insert($data, $returning);
    }

    /**
     * Insert many records at once
     * @param array $fieldNames - field names use
     * @param array[] $rows - arrays of values for $fieldNames
     * @param bool|string $returning - string: something compatible with RETURNING for postgresql query | false: do not return
     * @return int - amount of rows created
     * @throws DbQueryException
     */
    public function insertMany($fieldNames, $rows, $returning = false) {
        return $this->builder()->insertMany($fieldNames, $rows, $returning);
    }

    /**
     * Get records
     * @param array $data - associatine array, fields to update
     * @param null|array|string|int $conditionsAndOptions -
     *      array|string: conditions,
     *      numeric|int: record id, automatically converted to array('id' => $where)
     * @param null|bool|string $returning
     *      string: something compatible with RETURNING for postgresql query ('*' = all fields)
     *      array: list of fields to return
     *      null: return pk value
     *      true: return all fields ('*')
     *      false: return nothing
     * @return int|array
     */
    public function update($data, $conditionsAndOptions = null, $returning = false) {
        if (is_numeric($conditionsAndOptions) || is_int($conditionsAndOptions)) {
            $conditionsAndOptions = array('id' => $conditionsAndOptions);
        }
        return $this->builder()->fromOptions($conditionsAndOptions)->update($data, $returning);
    }

    /**
     * Delete some records by conditions
     * @param array|string|null $conditionsAndOptions
     * @param bool|string $returning - expression
     * @return int|array - PDOStatement returned only when $returning specified
     */
    public function delete($conditionsAndOptions = null, $returning = false) {
        return $this->builder()->fromOptions($conditionsAndOptions)->delete($returning);
    }

    /**
     * Make a query that returns only 1 value defined by $expression
     * @param string $expression - example: 'COUNT(*)', 'SUM(`field`)'
     * @param array|string|null $conditionsAndOptions
     * @return string|int|float|bool
     */
    public function expression($expression, $conditionsAndOptions = null) {
        return $this->builder()->expression($expression, $conditionsAndOptions);
    }

    public function exists($conditionsAndOptions) {
        return $this->expression('1', $conditionsAndOptions) == 1;
    }
    
    public function count($conditionsAndOptions = null) {
        if (is_array($conditionsAndOptions)) {
            unset($conditionsAndOptions['ORDER'], $conditionsAndOptions['LIMIT'], $conditionsAndOptions['OFFSET']);
        }
        return 0 + $this->expression('COUNT(*)', $conditionsAndOptions);
    }

    public function sum($column, $conditionsAndOptions = null) {
        return 0 + $this->expression("SUM(`$column`)", $conditionsAndOptions);
    }
    
    public function max($column, $conditionsAndOptions = null) {
        return 0 + $this->expression("MAX(`$column`)`", $conditionsAndOptions);
    }
    
    public function min($column, $conditionsAndOptions = null) {
        return 0 + $this->expression("MIN(`$column`)", $conditionsAndOptions);
    }
    
    public function avg($column, $conditionsAndOptions = null) {
        return 0 + $this->expression("AVG(`$column`)", $conditionsAndOptions);
    }
    
    public function lastQuery() {
        return $this->getDataSource()->lastQuery();
    }

    public function begin($readOnly = false, $transactionType = null) {
        $this->getDataSource()->begin($readOnly, $transactionType);
    }

    public function inTransaction() {
        return $this->getDataSource()->inTransaction();
    }

    public function commit() {
        $this->getDataSource()->commit();
    }

    public function rollback() {
        $this->getDataSource()->rollback();
    }

    public function quoteName($name) {
        return $this->getDataSource()->quoteName($name);
    }

    public function quoteValue($value, $fieldInfoOrType = \PDO::PARAM_STR) {
        return $this->getDataSource()->quoteValue($value, $fieldInfoOrType);
    }

    public function query($query) {
        return $this->getDataSource()->query($query);
    }

    public function exec($query) {
        return $this->getDataSource()->exec($query);
    }
}
