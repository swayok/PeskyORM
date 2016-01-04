<?php

namespace PeskyORM;
use PeskyORM\DbColumnConfig;
use PeskyORM\Exception\DbException;
use PeskyORM\Exception\DbModelException;
use PeskyORM\Exception\DbQueryException;
use PeskyORM\Exception\DbUtilsException;
use Swayok\Utils\Set;
use Swayok\Utils\StringUtils;

/**
 * Class Model
 */
abstract class DbModel {

    /** @var Db[] */
    static protected $dataSources = [];
    /** @var DbConnectionConfig[] */
    static protected $dbConnectionConfigs = [];
    /** @var DbModel[] */
    static protected $loadedModels = [];    //< Model objects

    const ORDER_ASCENDING = 'ASC';
    const ORDER_DESCENDING = 'DESC';

    /** @var DbTableConfig */
    protected $tableConfig;
    /** @var string */
    private $namespace;
    /** @var null|string */
    protected $alias = null;
    /** @var string|null */
    protected $orderField = null;
    /** @var string */
    protected $orderDirection = self::ORDER_ASCENDING;

    /**
     * When null - will be autogenerated using model's class name
     * Example: Model class = \App\Model\UserModel, genrated config class name = \App\Model\Config\UserTableConfig
     * @var null|string
     */
    protected $configClass = null;
    protected $configsNamespace;
    /**
     * @var array - additional conditions for relations to be used in JOINs = ['RelationAlias' => [condition1, condition2]]
     */
    public $relationsConditions = [];
    /**
     * In safe mode Db Objects will throw exceptions when they receive unknown field
     * @var bool
     */
    public $safeMode = true;

    static protected $tablesConfigsDirName = 'TableConfig';
    static protected $tableConfigClassSuffix = 'TableConfig';
    static protected $objectsClassesDirName = 'Object';
    static protected $modelClassSuffix = 'Model';
    static protected $modelsClassesDirName = 'Model';

    /**
     * @return $this
     * @throws DbUtilsException
     */
    static public function getInstance() {
        $calledClass = get_called_class();
        return call_user_func([$calledClass, 'getModelByClassName'], $calledClass);
    }

    static public function getModelClassSuffix() {
        $calledClass = get_called_class();
        return $calledClass::$modelClassSuffix;
    }

    static public function getTableConfigClassSuffix() {
        $calledClass = get_called_class();
        return $calledClass::$tableConfigClassSuffix;
    }

    /**
     * @throws DbModelException
     */
    public function __construct() {
        $className = get_class($this);
        if (!preg_match('%^(.*?\\\?)([a-zA-Z0-9]+)' . $this::$modelClassSuffix .'$%is', $className, $classNameParts)) {
            throw new DbModelException($this, "Invalid Model class name [{$className}]. Required name is like NameSpace\\SomeModel.");
        }
        $this->namespace = $classNameParts[1];
        $this->loadTableConfig($classNameParts[2]);
        if (empty($this->alias)) {
            $this->alias = StringUtils::modelize($this->tableConfig->getName());
        }
        if (empty($this->tableConfig->getName())) {
            throw new DbModelException($this, 'Model ' . $className . ' has no table name');
        }
        if (empty($this->tableConfig->getPk())) {
            throw new DbModelException($this, 'Model ' . $className . ' has no primary key');
        }
        if (empty($this->orderField)) {
            $this->orderField = $this->tableConfig->getPk();
        }
        if (empty($this->alias)) {
            $this->alias = StringUtils::modelize($this->getTableName());
        }
    }

    /**
     * @param $relationAlias
     * @return array
     */
    public function getAdditionalConditionsForRelation($relationAlias) {
        if (!empty($this->relationsConditions[$relationAlias])) {
            return $this->relationsConditions[$relationAlias];
        }
        return [];
    }

    /**
     * @return string
     */
    public function getNamespace() {
        return $this->namespace;
    }

    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableConfig->getName();
    }

    /**
     * @return string|null
     */
    public function getPkColumnName() {
        return $this->tableConfig->getPk();
    }

    /**
     * @return string|null
     */
    public function hasPkColumn() {
        return $this->tableConfig->hasPk();
    }

    /**
     * @return DbTableConfig
     * @throws DbModelException
     */
    public function getTableConfig() {
        if (!$this->hasTableConfig()) {
            throw new DbModelException($this, "DB table config not loaded");
        }
        return $this->tableConfig;
    }

    /**
     * @return bool
     */
    public function hasTableConfig() {
        return !empty($this->tableConfig);
    }

    /**
     * @return DbRelationConfig[]
     */
    public function getTableRealtaions() {
        return $this->getTableConfig()->getRelations();
    }

    /**
     * @param string $alias
     * @return DbRelationConfig
     * @throws DbModelException
     * @throws \PeskyORM\Exception\DbTableConfigException
     */
    public function getTableRealtaion($alias) {
        return $this->getTableConfig()->getRelation($alias);
    }

    /**
     * @param string $alias
     * @return bool
     * @throws DbModelException
     */
    public function hasTableRelation($alias) {
        return $this->getTableConfig()->hasRelation($alias);
    }

    /**
     * @return DbColumnConfig[]
     */
    public function getTableColumns() {
        return $this->getTableConfig()->getColumns();
    }

    /**
     * @param string $colName
     * @return DbColumnConfig
     * @throws DbModelException
     * @throws \PeskyORM\Exception\DbTableConfigException
     */
    public function getTableColumn($colName) {
        return $this->getTableConfig()->getColumn($colName);
    }

    /**
     * @param string $colName
     * @return bool
     * @throws DbModelException
     */
    public function hasTableColumn($colName) {
        return $this->getTableConfig()->hasColumn($colName);
    }

    /**
     * @return null|string
     */
    public function getAlias() {
        return $this->alias;
    }

    /**
     * @param string $alias
     * @return $this
     */
    public function setAlias($alias) {
        $this->alias = $alias;
        return $this;
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
     * @return null|string
     */
    public function getOrderField() {
        return $this->orderField;
    }

    /**
     * @param null|string $orderField
     * @return $this
     */
    public function setOrderField($orderField) {
        $this->orderField = $orderField;
        return $this;
    }

    /**
     * @return string
     */
    public function getOrderDirection() {
        return $this->orderDirection;
    }

    /**
     * @param string $orderDirection
     * @return $this
     */
    public function setOrderDirection($orderDirection) {
        $this->orderDirection = $orderDirection;
        return $this;
    }

    /**
     * Load config class
     * @param string $modelName
     * @throws DbModelException
     */
    public function loadTableConfig($objectName) {
        if (empty($this->configClass)) {
            $this->configClass = $this->getConfigsNamespace() . $this->getTableConfigNameByObjectName($objectName);
        }
        if (!class_exists($this->configClass)) {
            throw new DbModelException($this, "Db table config class [{$this->configClass}] not found");
        }
        $this->setTableConfig(call_user_func([$this->configClass, 'getInstance']));
    }

    /**
     * Loads models by class name. Example: Model::User() will create object of class User (or pick existing if already exists)
     * @param $modelOrObjectName - class name or table name (UserTokenModel, UserToken or user_tokens)
     * @param array $objectArgs - used only for DbObjects to pass data array or primary key value
     * @return DbModel|DbObject
     * @throws DbModelException
     */
    static public function __callStatic($modelOrObjectName, $objectArgs = []) {
        $calledClass = get_called_class();
        if (preg_match('%^(.*)' . $calledClass::$modelClassSuffix . '$%s', $modelOrObjectName, $matches)) {
            // model requested
            return call_user_func([$calledClass, 'getModel'], $modelOrObjectName);
        } else {
            // db object requested
            return call_user_func(
                [$calledClass, 'createDbObject'],
                $modelOrObjectName,
                !empty($objectArgs) ? $objectArgs[0] : null,
                !empty($objectArgs) && isset($objectArgs[1]) ? $objectArgs[1] : null
            );
        }
    }

    /**
     * Load and return requested Model
     * @param string $modelNameOrObjectName - class name (UserToken or UserTokenModel o User)
     * @return DbModel
     * @throws DbUtilsException
     */
    static public function getModel($modelNameOrObjectName) {
        // todo: maybe use reflections?
        // load model if not loaded yet
        $calledClass = get_called_class();
        if (!preg_match('%' . $calledClass::$modelClassSuffix . '$%i', $modelNameOrObjectName)) {
            $modelNameOrObjectName .= $calledClass::$modelClassSuffix;
        }
        $modelClass = call_user_func([$calledClass, 'getModelsNamespace']) . $modelNameOrObjectName;
        return call_user_func([$calledClass, 'getModelByClassName'], $modelClass);
    }

    /**
     * @param string $modelClass
     * @return DbModel
     * @throws DbUtilsException
     */
    static public function getModelByClassName($modelClass) {
        if (empty(self::$loadedModels[$modelClass])) {
            if (!class_exists($modelClass)) {
                throw new DbUtilsException("Class $modelClass was not found");
            }
            self::$loadedModels[$modelClass] = new $modelClass();
        }
        return self::$loadedModels[$modelClass];
    }

    /**
     * Get related model by relation alias
     * @param string $relationAlias
     * @return DbModel
     * @throws DbModelException
     */
    public function getRelatedModel($relationAlias) {
        if (!$this->hasTableRelation($relationAlias)) {
            throw new DbModelException($this, "Unknown relation with alias [$relationAlias]");
        }
        $foreignTable = $this->getTableRealtaion($relationAlias)->getForeignTable();
        $relatedModelClass = $this->getFullModelClassByTableName($foreignTable);
        if (!class_exists($relatedModelClass)) {
            throw new DbModelException($this, "Related model class [{$relatedModelClass}] not found for relation [{$relationAlias}]");
        }
        return $this->getModel($this->getModelNameByTableName($foreignTable));
    }

    /**
     * @param DbRelationConfig $relation
     * @return DbObject
     * @throws DbModelException
     */
    public function getRelatedObject($relationAlias) {
        return $this->getRelatedModel($relationAlias)->getOwnDbObject();
    }

    /**
     * @param $relationAlias
     * @return mixed
     * @throws DbModelException
     */
    public function getRelatedObjectClass($relationAlias) {
        return $this->getModel()->getFullDbObjectClass($this->getTableRealtaion($relationAlias)->getForeignTable());
    }

    /**
     * Convert get_class($this) to db object class name (without namespace)
     * @return string
     */
    public function dbObjectName() {
        return $this->dbObjectNameByModelClassName(get_class($this));
    }

    /**
     * Convert get_class($this) to db object class name (without namespace)
     * @param string $class - class name. Must end on 'Model'
     * @return string
     */
    static public function dbObjectNameByModelClassName($class) {
        $calledClass = get_called_class();
        return preg_replace(
            [
                '%^.*[\\\]%is',
                '%' . $calledClass::$modelClassSuffix . '$%',
                '%^' . preg_quote(addslashes(call_user_func([$calledClass, 'getModelsNamespace'])), '%') . '/%'
            ],
            [
                '',
                '',
                call_user_func([$calledClass, 'getObjectsNamespace']) . '/'
            ],
            $class
        );
    }

    /**
     * Load DbObject class and create new instance of it
     * @param string $dbObjectNameOrTableName - class name or table name (UserToken or user_tokens)
     * @param null|array|string|int $data - null: do nothing | int and string: is primary key (read db) | array: object data
     * @param bool $filter - used only when $data not empty and is array
     *      true: filters $data that does not belong to this object
     *      false: $data that does not belong to this object will trigger exceptions
     * @param bool $isDbValues
     * @return DbObject
     * @throws DbUtilsException
     */
    static public function createDbObject($dbObjectNameOrTableName, $data = null, $filter = false, $isDbValues = false) {
        $calledClass = get_called_class();
        $dbObjectClass = call_user_func([$calledClass, 'getFullDbObjectClass'], $dbObjectNameOrTableName);
        if (!class_exists($dbObjectClass)) {
            throw new DbUtilsException("Class $dbObjectClass was not found");
        }
        $model = call_user_func([$calledClass, 'getModel'], StringUtils::modelize($dbObjectNameOrTableName));
        return new $dbObjectClass($data, $filter, $isDbValues, $model);
    }

    /**
     * Get DbObject class with name space
     * @param string $dbObjectNameOrTableName - object class or db table name.
     * @return string
     */
    static public function getFullDbObjectClass($dbObjectNameOrTableName) {
        return call_user_func([get_called_class(), 'getObjectsNamespace']) . StringUtils::modelize($dbObjectNameOrTableName);
    }

    static public function getModelsNamespace() {
        return preg_replace('%[a-zA-Z0-9_]+$%', '', get_called_class());
    }

    static public function getObjectsNamespace() {
        $calledClass = get_called_class();
        return preg_replace(
            '%[a-zA-Z0-9_]+\\\$%',
            $calledClass::$objectsClassesDirName . '\\',
            call_user_func([$calledClass, 'getModelsNamespace'])
        );
    }

    protected function getConfigsNamespace() {
        if (empty($this->configsNamespace)) {
            $this->configsNamespace = preg_replace('%^(.*)\\\.+?$%s', '$1', $this->namespace) . '\\' . $this::$tablesConfigsDirName .'\\';
        }
        return $this->configsNamespace;
    }

    static public function getFullModelClassByTableName($tableName) {
        $calledClass = get_called_class();
        $ns = call_user_func([$calledClass, 'getModelsNamespace']);
        return $ns . call_user_func([$calledClass, 'getModelNameByTableName'], $tableName);
    }

    static public function getModelNameByTableName($tableName) {
        $calledClass = get_called_class();
        return call_user_func([$calledClass, 'getObjectNameByTableName'], $tableName) . $calledClass::$modelClassSuffix;
    }

    static public function getObjectNameByTableName($tableName) {
        return StringUtils::modelize($tableName);
    }

    static public function getTableConfigNameByObjectName($objectName) {
        $calledClass = get_called_class();
        return $objectName . $calledClass::$tableConfigClassSuffix;
    }

    static public function getTableConfigNameByTableName($tableName) {
        $calledClass = get_called_class();
        return call_user_func(
            [$calledClass, 'getTableConfigNameByObjectName'],
            call_user_func([$calledClass, 'getObjectNameByTableName'], $tableName)
        );
    }

    /**
     * @param $objectClass
     * @return string
     */
    static public function getModelByObjectClass($objectClass) {
        $calledClass = get_called_class();
        return call_user_func([get_called_class(), 'getModel'], preg_replace('%^.*\\\%', '', $objectClass));
    }

    /**
     * Load DbObject for current model and create new instance of it
     * @param null|array|string|int $data - null: do nothing | int and string: is primary key (read db) | array: object data
     * @param bool $filter - used only when $data not empty and is array
     *      true: filters $data that does not belong to this object
     *      false: $data that does not belong to this object will trigger exceptions
     * @param bool $isDbValues
     * @return DbObject
     * @throws DbUtilsException
     */
    static public function getOwnDbObject($data = null, $filter = false, $isDbValues = false) {
        $calledClass = get_called_class();
        return call_user_func(
            [$calledClass, 'createDbObject'],
            call_user_func([$calledClass, 'dbObjectNameByModelClassName'], $calledClass),
            $data,
            $filter,
            $isDbValues
        );
    }

    /**
     * Collect real DB fields excluding virtual fields like files and images
     * @return array
     */
    public function getDbFields() {
        $ret = [];
        foreach ($this->getTableColumns() as $name => $column) {
            if ($column->isExistsInDb()) {
                $ret[] = $name;
            }
        }
        return $ret;
    }

    /**
     * @param DbConnectionConfig $dbConnectionConfig
     * @param $alias
     */
    static public function setDbConnectionConfig(DbConnectionConfig $dbConnectionConfig, $alias = 'default') {
        self::$dbConnectionConfigs[$alias] = $dbConnectionConfig;
    }

    /**
     * @return string
     */
    public function getConnectionAlias() {
        return $this->getTableConfig()->getConnectionAlias();
    }

    /**
     * Get data source object
     * @param string $alias
     * @return Db
     * @throws DbModelException
     * @throws \PeskyORM\Exception\DbConnectionConfigException
     */
    public function getDataSource($alias = null) {
        if (empty($alias)) {
            $alias = $this->getConnectionAlias();
        }
        return self::_getDataSource($alias);
    }

    /**
     * @param string $alias
     * @return Db
     * @throws DbUtilsException
     * @throws Exception\DbConnectionConfigException
     */
    static public function _getDataSource($alias) {
        if (empty(self::$dataSources[$alias])) {
            if (empty(self::$dbConnectionConfigs[$alias])) {
                throw new DbUtilsException("Unknown DB connection with alias [$alias]");
            }
            self::$dataSources[$alias] = new Db(
                self::$dbConnectionConfigs[$alias]->getDriver(),
                self::$dbConnectionConfigs[$alias]->getDbName(),
                self::$dbConnectionConfigs[$alias]->getUserName(),
                self::$dbConnectionConfigs[$alias]->getPassword(),
                self::$dbConnectionConfigs[$alias]->getHost()
            );
        }
        return self::$dataSources[$alias];
    }

    /**
     * @param string $alias
     * @return bool
     */
    public function hasConnectionToDataSource($alias = null) {
        if (empty($alias) && $this->hasTableConfig()) {
            $alias = $this->getConnectionAlias();
        }
        return !empty(self::$dataSources[$alias]);
    }

    /**
     * Convert records to DbObjects
     * @param array $records
     * @param bool $dataIsLoadedFromDb
     * @return array
     */
    public function recordsToObjects($records, $dataIsLoadedFromDb = false) {
        if (is_array($records) && !empty($records)) {
            $objects = [];
            foreach ($records as $record) {
                if ($dataIsLoadedFromDb) {
                    $objects[] = $this->getOwnDbObject($record, false, true);
                } else {
                    $objects[] = $this->getOwnDbObject($record);
                }
            }
            return $objects;
        }
        return $records;
    }

    /**
     * Build valid 'JOIN' settings from 'CONTAIN' table aliases
     * @param array $where
     * @return mixed $where
     * @throws DbModelException
     */
    public function resolveContains($where) {
        if (is_array($where)) {
            if (!empty($where['CONTAIN'])) {
                if (!is_array($where['CONTAIN'])) {
                    $where['CONTAIN'] = [$where['CONTAIN']];
                }
                $where['JOIN'] = [];

                foreach ($where['CONTAIN'] as $alias => $fields) {
                    if (is_int($alias)) {
                        $alias = $fields;
                        $fields = !empty($relation['fields']) ? $relation['fields'] : '*';
                    }
                    $relationConfig = $this->getTableRealtaion($alias);
                    if ($relationConfig->getType() === DbRelationConfig::HAS_MANY) {
                        throw new DbModelException($this, "Queries with one-to-many joins are not allowed via 'CONTAIN' key");
                    } else {
                        $model = $this->getRelatedModel($alias);
                        $additionalConditions = $relationConfig->getAdditionalJoinConditions();
                        $joinType = $relationConfig->getJoinType();
                        if (is_array($fields)) {
                            if (isset($fields['TYPE'])) {
                                $joinType = $fields['TYPE'];
                            }
                            unset($fields['TYPE']);
                            if (isset($fields['CONDITIONS'])) {
                                $additionalConditions = array_replace_recursive($additionalConditions, $fields['CONDITIONS']);
                            }
                            unset($fields['CONDITIONS']);
                            if (!empty($fields['CONTAIN'])) {
                                $subContains = ['CONTAIN' => $fields['CONTAIN']];
                            }
                            unset($fields['CONTAIN']);
                            if (empty($fields)) {
                                $fields = '*';
                            }
                        }

                        $where['JOIN'][$alias] = DbJoinConfig::create($alias)
                            ->setConfigForLocalTable($this, $relationConfig->getColumn())
                            ->setJoinType($joinType)
                            ->setConfigForForeignTable($model, $relationConfig->getForeignColumn())
                            ->setAdditionalJoinConditions($additionalConditions)
                            ->setForeignColumnsToSelect($fields)
                            ->getConfigsForDbQuery();

                        if (!empty($subContains)) {
                            $subJoins = $model->resolveContains($subContains);
                            $where['JOIN'] = array_merge($where['JOIN'], $subJoins['JOIN']);
                        }
                    }
                }
                if (empty($where['JOIN'])) {
                    unset($where['JOIN']);
                }
            }
            unset($where['CONTAIN']);
        }
        return $where;
    }

    /**
     * Add columns into options and resolve contains
     * @param mixed $columns
     * @param mixed $options
     * @return array|mixed
     */
    protected function prepareSelect($columns, $options) {
        if (!is_array($options)) {
            if (!empty($options) && is_string($options)) {
                $options = [$options];
            } else {
                $options = [];
            }
        } else {
            $options = $this->resolveContains($options);
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
     * @return array|DbObject[]
     */
    public function select($columns = '*', $conditionsAndOptions = null, $asObjects = false, $withRootAlias = false) {
        $records = $this->builder()
            ->fromOptions($this->prepareSelect($columns, $conditionsAndOptions))
            ->find('all', $withRootAlias);
        if ($asObjects) {
            $records = $this->recordsToObjects($records, true);
        }
        return $records;
    }

    /**
     * Selects only 1 column
     * @param string $column
     * @param null|array|string $conditionsAndOptions
     * @return array
     */
    public function selectColumn($column, $conditionsAndOptions = null) {
        $records = $this->select(['value' => $column], $conditionsAndOptions, false, false);
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
        $records = $this->select(['key' => $keysColumn, 'value' => $valuesColumn], $conditionsAndOptions, false, false);
        $res = [];
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
     * @return array - 'count' => int, 'records' => array)
     */
    public function selectWithCount($columns, $conditionsAndOptions = null, $asObjects = false) {
        $conditionsAndOptions = $this->prepareSelect($columns, $conditionsAndOptions);
        $count = $this->count($conditionsAndOptions);
        if (empty($count)) {
            return ['records' => [], 'count' => 0];
        }
        $results = [
            'records' => $this->select($columns, $conditionsAndOptions, false, false),
            'count' => $count
        ];
        if ($asObjects) {
            $results['records'] = $this->recordsToObjects($results['records']);
        }
        return $results;
    }

    /**
     * Get 1 record from DB
     * @param string|array $columns
     * @param array|string|int $conditionsAndOptions -
     *      array|string: conditions,
     *      numeric|int: record's pk value, automatically converted to [$this->primaryKey => $where]
     * @param bool $asObject - true: return DbObject | false: return array
     * @param bool $withRootAlias
     * @return array|bool|DbObject
     * @throws DbModelException
     * @throws \PeskyORM\Exception\DbQueryException
     */
    public function selectOne($columns, $conditionsAndOptions, $asObject = false, $withRootAlias = false) {
        if (empty($conditionsAndOptions)){
            throw new DbModelException($this, 'Selecting one record without conditions is not allowed');
        }
        if (is_numeric($conditionsAndOptions) || is_int($conditionsAndOptions)) {
            $conditionsAndOptions = [$this->getPkColumnName() => $conditionsAndOptions];
        }
        $record = $this->builder()
            ->fromOptions($this->prepareSelect($columns, $conditionsAndOptions))
            ->findOne($withRootAlias);
        if (!is_array($record)) {
            return $record;
        } else if ($asObject) {
            return $this->getOwnDbObject($record, false, true);
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
     * @return int|array - int: amount of rows created | array: records (when $returning !== false)
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
     *      numeric|int: record id, automatically converted to ['id' => $where]
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
            $conditionsAndOptions = [$this->getPkColumnName() => $conditionsAndOptions];
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
        return $this->builder()->expression($expression, $this->resolveContains($conditionsAndOptions));
    }

    public function exists($conditionsAndOptions) {
        $conditionsAndOptions['LIMIT'] = 1;
        return $this->expression('1', $conditionsAndOptions) == 1;
    }

    public function count($conditionsAndOptions = null, $removeNotInnerJoins = false) {
        if (is_array($conditionsAndOptions)) {
            unset($conditionsAndOptions['ORDER'], $conditionsAndOptions['LIMIT'], $conditionsAndOptions['OFFSET']);
        }
        $conditionsAndOptions = $this->resolveContains($conditionsAndOptions);
        if ($removeNotInnerJoins) {
            // remove left joins for count query - they will not affect result but will slow down query
            if (!empty($conditionsAndOptions['JOIN'])) {
                foreach ($conditionsAndOptions['JOIN'] as $key => $options) {
                    if ($options['type'] === DbRelationConfig::JOIN_LEFT) {
                        unset($conditionsAndOptions['JOIN'][$key]);
                    }
                }
            }
        }
        return 0 + $this->expression('COUNT(*)', $conditionsAndOptions);
    }

    public function sum($column, $conditionsAndOptions = null) {
        return 0 + $this->expression("SUM(`$column`)", $conditionsAndOptions);
    }

    public function max($column, $conditionsAndOptions = null) {
        return 0 + $this->expression("MAX(`$column`)", $conditionsAndOptions);
    }

    public function min($column, $conditionsAndOptions = null) {
        return 0 + $this->expression("MIN(`$column`)", $conditionsAndOptions);
    }

    public function avg($column, $conditionsAndOptions = null) {
        return 0 + $this->expression("AVG(`$column`)", $conditionsAndOptions);
    }

    public function getLastQuery() {
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

    /**
     * @param string $expression
     * @return string
     */
    public function replaceQuotes($expression) {
        return $this->getDataSource()->replaceQuotes($expression);
    }

    public function query($query) {
        return $this->getDataSource()->query($query);
    }

    public function exec($query) {
        return $this->getDataSource()->exec($query);
    }
}
