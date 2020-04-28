<?php

namespace PeskyORM;
use http\Exception\UnexpectedValueException;
use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORM\ORM\Table;
use Swayok\Utils\StringUtils;

/**
 * Class Model
 */
abstract class DbModel extends Table {

    /** @var DbModel[] */
    static protected $loadedModels = [];    //< Model objects

    const ORDER_ASCENDING = 'ASC';
    const ORDER_DESCENDING = 'DESC';

    /** @var DbTableConfig */
    protected $tableConfig;
    /** @var string */
    protected $namespace;
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

    static protected $tablesConfigsDirName = 'TableConfig';
    static protected $tableConfigClassSuffix = 'TableConfig';
    static protected $objectsClassesDirName = 'Object';
    static protected $modelClassSuffix = 'Model';
    
    /**
     * @deprecated
     * @return string
     */
    static public function getModelClassSuffix() {
        /** @var self $calledClass */
        $calledClass = get_called_class();
        return $calledClass::$modelClassSuffix;
    }
    
    /**
     * @deprecated
     * @return string
     */
    static public function getTableConfigClassSuffix() {
        /** @var self $calledClass */
        $calledClass = get_called_class();
        return $calledClass::$tableConfigClassSuffix;
    }
    
    public function __construct() {
        $this->loadTableConfig();
    }

    /**
     * @deprecated
     * @return string
     */
    public function getNamespace() {
        return $this->namespace;
    }

    /**
     * @deprecated
     * @return string
     */
    public function getTableName() {
        return static::getName();
    }

    /**
     * @deprecated
     * @return DbTableConfig
     */
    public function getTableConfig() {
        return $this->getTableStructure();
    }
    
    public static function getStructure() {
        return static::getInstance()->getTableStructure();
    }
    
    public function getTableStructure() {
        return $this->tableConfig;
    }
    
    /**
     * @return DbRelationConfig[]|Relation[]
     */
    public function getTableRealtaions() {
        return $this->getTableStructure()->getRelations();
    }

    /**
     * @param string $alias
     * @return DbRelationConfig|Relation
     */
    public function getTableRealtaion($alias) {
        return $this->getTableStructure()->getRelation($alias);
    }

    /**
     * @param string $alias
     * @return bool
     */
    public function hasTableRelation($alias) {
        return $this->getTableStructure()->hasRelation($alias);
    }

    /**
     * @return Column[]
     */
    public function getTableColumns() {
        return $this->getTableStructure()->getColumns();
    }

    /**
     * @param string $colName
     * @return Column
     */
    public function getTableColumn($colName) {
        return $this->getTableStructure()->getColumn($colName);
    }

    /**
     * @param string $colName
     * @return bool
     */
    public function hasTableColumn($colName) {
        return $this->getTableStructure()->hasColumn($colName);
    }

    /**
     * @deprecated
     * @param DbTableConfig $tableConfig
     * @return $this
     */
    protected function setTableConfig($tableConfig) {
        $this->tableConfig = $tableConfig;
        return $this;
    }

    /**
     * @deprecated
     * @return null|string
     */
    public function getOrderField() {
        return $this->orderField;
    }

    /**
     * @deprecated
     * @return string
     */
    public function getOrderDirection() {
        return $this->orderDirection;
    }

    /**
     * @deprecated
     * Load config class
     * @param string $modelName
     */
    protected function loadTableConfig() {
        $className = get_class($this);
        if (!preg_match('%^(.*?\\\?)([a-zA-Z0-9]+)' . static::getModelClassSuffix() . '$%is', $className, $classNameParts)) {
            throw new \UnexpectedValueException("Invalid Model class name [{$className}]. Required name is like NameSpace\\SomeModel.");
        }
        $this->namespace = $classNameParts[1];
        if (empty($this->configClass)) {
            $this->configClass = $this->getConfigsNamespace() . static::getTableConfigNameByObjectName($classNameParts[2]);
        }
        if (!class_exists($this->configClass)) {
            throw new UnexpectedValueException("Db table config class [{$this->configClass}] not found");
        }
        $this->setTableConfig(call_user_func([$this->configClass, 'getInstance']));
    }

    /**
     * @deprecated
     * Load and return requested Model
     * @param string $modelNameOrObjectName - base class name (UserToken or UserTokenModel or User)
     * @return DbModel
     */
    static public function getModel($modelNameOrObjectName) {
        /** @var DbModel $calledClass */
        $calledClass = static::class;
        $modelClass = $calledClass::getFullModelClassNameByName($modelNameOrObjectName);
        return $calledClass::getModelByClassName($modelClass);
    }

    /**
     * @deprecated
     * @param string $modelNameOrObjectName - base class name (UserToken or UserTokenModel or User)
     * @return string
     */
    static public function getFullModelClassNameByName($modelNameOrObjectName) {
        /** @var DbModel $calledClass */
        $calledClass = static::class;
        $modelNameOrObjectName = preg_replace(
            '%' . $calledClass::$modelClassSuffix . '$%i',
            $calledClass::$modelClassSuffix,
            $modelNameOrObjectName
        );
        return $calledClass::getModelsNamespace() . $modelNameOrObjectName;
    }

    /**
     * @deprecated
     * @param string $modelClass
     * @return $this
     */
    static public function getModelByClassName($modelClass) {
        if (empty(self::$loadedModels[$modelClass])) {
            if (!class_exists($modelClass)) {
                throw new \InvalidArgumentException("Class $modelClass was not found");
            }
            self::$loadedModels[$modelClass] = new $modelClass();
        }
        return self::$loadedModels[$modelClass];
    }

    /**
     * @deprecated
     * Convert get_class($this) to db object class name (without namespace)
     * @param string $class - class name. Must end on 'Model'
     * @return string
     */
    static public function dbObjectNameByModelClassName($class) {
        /** @var DbModel $calledClass */
        $calledClass = static::class;
        return preg_replace(
            [
                '%^.*[\\\]%is',
                '%' . $calledClass::$modelClassSuffix . '$%',
                '%^' . preg_quote(addslashes($calledClass::getModelsNamespace()), '%') . '/%'
            ],
            [
                '',
                '',
                $calledClass::getObjectsNamespace() . '/'
            ],
            $class
        );
    }
    
    /**
     * @param string $tableName
     * @return $this
     */
    static public function getModelByTableName($tableName) {
        $modelClass = static::getFullModelClassByTableName($tableName);
        return static::getModelByClassName($modelClass);
    }

    /**
     * @deprecated
     * Load DbObject class and create new instance of it
     * @param string $dbObjectNameOrTableName - class name or table name (UserToken or user_tokens)
     * @param null|array|string|int $data - null: do nothing | int and string: is primary key (read db) | array: object data
     * @param bool $filter - used only when $data not empty and is array
     *      true: filters $data that does not belong to this object
     *      false: $data that does not belong to this object will trigger exceptions
     * @param bool $isDbValues
     * @return DbObject
     */
    static public function createDbObject($dbObjectNameOrTableName, $data = null, $filter = false, $isDbValues = false) {
        $dbObjectClass = static::getFullDbObjectClass($dbObjectNameOrTableName);
        if (!class_exists($dbObjectClass)) {
            throw new \InvalidArgumentException("Class $dbObjectClass was not found");
        }
        $model = static::getModel(StringUtils::modelize($dbObjectNameOrTableName));
        return new $dbObjectClass($data, $filter, $isDbValues, $model);
    }
    
    /**
     * @deprecated
     * Get DbObject class with name space
     * @param string $dbObjectNameOrTableName - object class or db table name.
     * @return string
     */
    static public function getFullDbObjectClass($dbObjectNameOrTableName) {
        /** @var DbModel $calledClass */
        $calledClass = static::class;
        return $calledClass::getObjectsNamespace() . StringUtils::modelize($dbObjectNameOrTableName);
    }
    
    /**
     * @deprecated
     * @return string|string[]|null
     */
    static public function getModelsNamespace() {
        return preg_replace('%[a-zA-Z0-9_]+$%', '', get_called_class());
    }
    
    /**
     * @deprecated
     * @return string|string[]|null
     */
    static public function getObjectsNamespace() {
        /** @var DbModel $calledClass */
        $calledClass = static::class;
        return preg_replace(
            '%[a-zA-Z0-9_]+\\\$%',
            $calledClass::$objectsClassesDirName . '\\',
            $calledClass::getModelsNamespace()
        );
    }
    
    /**
     * @deprecated
     * @return string
     */
    protected function getConfigsNamespace() {
        if (empty($this->configsNamespace)) {
            $this->configsNamespace = preg_replace('%^(.*)\\\.+?$%s', '$1', $this->namespace) . '\\' . $this::$tablesConfigsDirName .'\\';
        }
        return $this->configsNamespace;
    }
    
    /**
     * @deprecated
     * @param $tableName
     * @return string
     */
    static public function getFullModelClassByTableName($tableName) {
        /** @var DbModel $calledClass */
        $calledClass = static::class;
        $ns = $calledClass::getModelsNamespace();
        return $ns . $calledClass::getModelNameByTableName($tableName);
    }
    
    /**
     * @deprecated
     * @param $tableName
     * @return string
     */
    static public function getModelNameByTableName($tableName) {
        /** @var DbModel $calledClass */
        $calledClass = static::class;
        return $calledClass::getObjectNameByTableName($tableName) . $calledClass::$modelClassSuffix;
    }
    
    /**
     * @deprecated
     * @param $tableName
     * @return string
     */
    static public function getObjectNameByTableName($tableName) {
        return StringUtils::modelize($tableName);
    }
    
    /**
     * @deprecated
     * @param $objectName
     * @return string
     */
    static public function getTableConfigNameByObjectName($objectName) {
        /** @var DbModel $calledClass */
        $calledClass = static::class;
        return $objectName . $calledClass::getTableConfigClassSuffix();
    }
    
    /**
     * @deprecated
     * @param $tableName
     * @return mixed
     */
    static public function getTableConfigNameByTableName($tableName) {
        /** @var DbModel $calledClass */
        $calledClass = static::class;
        return call_user_func(
            $calledClass::getTableConfigNameByObjectName(),
            $calledClass::getObjectNameByTableName($tableName)
        );
    }

    /**
     * @deprecated
     * @param $objectClass
     * @return DbModel
     */
    static public function getModelByObjectClass($objectClass) {
        /** @var DbModel $class */
        $class = static::class;
        
        return $class::getModel(preg_replace('%^.*\\\%', '', $objectClass));
    }

    /**
     * @deprecated
     * Load DbObject for current model and create new instance of it
     * @param null|array|string|int $data - null: do nothing | int and string: is primary key (read db) | array: object data
     * @param bool $filter - used only when $data not empty and is array
     *      true: filters $data that does not belong to this object
     *      false: $data that does not belong to this object will trigger exceptions
     * @param bool $isDbValues
     * @return DbObject
     */
    static public function getOwnDbObject($data = null, $filter = false, $isDbValues = false) {
        return static::createDbObject(
            static::dbObjectNameByModelClassName(get_called_class()),
            $data,
            $filter,
            $isDbValues
        );
    }
    
    /**
     * @return DbObject
     */
    public function newRecord() {
        return static::getOwnDbObject();
    }

    /**
     * @deprecated
     * Collect real DB fields excluding virtual fields like files and images
     * @return Column[]|DbColumnConfig[]
     */
    public function getDbFields() {
        return $this->getTableStructure()->getColumnsThatExistInDb();
    }

    /**
     * @deprecated
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
                    $objects[] = static::getOwnDbObject($record, false, true);
                } else {
                    $objects[] = static::getOwnDbObject($record);
                }
            }
            return $objects;
        }
        return $records;
    }

    /**
     * Build valid 'JOIN' settings from 'CONTAIN' table aliases
     * @param array $columnsToSelect
     * @param array $conditionsAndOptions
     * @param string|null $aliasForSubContains
     * @return array $additionalColumnsToSelect
     */
    static public function normalizeConditionsAndOptions(array $columnsToSelect, array $conditionsAndOptions, $aliasForSubContains = null) {
        if (!empty($conditionsAndOptions['CONTAIN'])) {
            if (!is_array($conditionsAndOptions['CONTAIN'])) {
                $conditionsAndOptions['CONTAIN'] = [$conditionsAndOptions['CONTAIN']];
            }
            if (empty($conditionsAndOptions['JOIN']) || !is_array($conditionsAndOptions['JOIN'])) {
                $conditionsAndOptions['JOIN'] = [];
            }

            foreach ($conditionsAndOptions['CONTAIN'] as $alias => $columnsToSelectForRelation) {
                if (is_int($alias)) {
                    $alias = $columnsToSelectForRelation;
                    $columnsToSelectForRelation = ['*'];
                } else {
                    $columnsToSelectForRelation = [];
                }
                $relationConfig = static::getStructure()->getRelation($alias);
                if ($relationConfig->getType() === Relation::HAS_MANY) {
                    throw new \UnexpectedValueException("Queries with one-to-many joins are not allowed via 'CONTAIN' key");
                } else {
                    $model = $relationConfig->getForeignTable();
                    $joinType = $relationConfig->getJoinType();
                    if (is_array($columnsToSelectForRelation)) {
                        if (isset($columnsToSelectForRelation['TYPE'])) {
                            $joinType = $columnsToSelectForRelation['TYPE'];
                        }
                        unset($columnsToSelectForRelation['TYPE']);
                        if (isset($columnsToSelectForRelation['CONDITIONS'])) {
                            throw new \UnexpectedValueException('CONDITIONS key is not supported in CONTAIN');
                        }
                        unset($columnsToSelectForRelation['CONDITIONS']);
                        if (!empty($columnsToSelectForRelation['CONTAIN'])) {
                            $subContains = $columnsToSelectForRelation['CONTAIN'];
                        }
                        unset($columnsToSelectForRelation['CONTAIN']);
                        if (empty($columnsToSelectForRelation)) {
                            $columnsToSelectForRelation = [];
                        }
                    }

                    $conditionsAndOptions['JOIN'][$alias] = $relationConfig->toOrmJoinConfig(
                        static::getInstance(),
                        $aliasForSubContains,
                        $alias,
                        $joinType
                    )->setForeignColumnsToSelect($columnsToSelectForRelation);

                    if (!empty($subContains)) {
                        [, $subJoins] = $model::normalizeConditionsAndOptions(
                            [],
                            ['CONTAIN' => $subContains],
                            $alias
                        );
                        $conditionsAndOptions['JOIN'] = array_merge($conditionsAndOptions['JOIN'], $subJoins['JOIN']);
                    }
                }
            }
            if (empty($conditionsAndOptions['JOIN'])) {
                unset($conditionsAndOptions['JOIN']);
            }
        }
        unset($conditionsAndOptions['CONTAIN']);
        if (!empty($conditionsAndOptions['ORDER'])) {
            if (is_string($conditionsAndOptions['ORDER'])) {
                $conditionsAndOptions['ORDER'] = explode(',', $conditionsAndOptions['ORDER']);
            }
            $orderBy = [];
            $orderRegexp = '%^([^\s]+)\s+((?:asc|desc)(?:\s+nulls\s+(?:first|last))?)$%i';
            foreach ($conditionsAndOptions['ORDER'] as $key => $value) {
                if (!is_int($key) || $value instanceof \PeskyORM\Core\DbExpr) {
                    $orderBy[$key] = $value;
                } else if (is_string($value) && preg_match($orderRegexp, trim($value), $matches)) {
                    $orderBy[$matches[1]] = $matches[2];
                } else {
                    throw new \UnexpectedValueException('ORDER key is invalid: ' . json_encode(['key' => $key, 'value' => $value], JSON_UNESCAPED_UNICODE));
                }
            }
            if (!empty($orderBy)) {
                $conditionsAndOptions['ORDER'] = $orderBy;
            }
        }
        return [$columnsToSelect, $conditionsAndOptions];
    }

    /* Queries */
    
    static public function select($columns = '*', array $conditionsAndOptions = [], \Closure $configurator = null, bool $asRecordSet = false) {
        [$columns, $conditionsAndOptions] = static::normalizeConditionsAndOptions((array)$columns, $conditionsAndOptions);
        $records = parent::select($columns, $conditionsAndOptions, null);
        return $asRecordSet ? $records : $records->toArrays();
    }

    static public function selectColumn($column, array $conditionsAndOptions = [], \Closure $configurator = null) {
        [, $conditionsAndOptions] = static::normalizeConditionsAndOptions([], $conditionsAndOptions);
        return parent::selectColumn($column, $conditionsAndOptions);
    }

    static public function selectAssoc($keysColumn, $valuesColumn, array $conditionsAndOptions = [], \Closure $configurator = null) {
        [, $conditionsAndOptions] = static::normalizeConditionsAndOptions([], $conditionsAndOptions);
        return parent::selectAssoc($keysColumn, $valuesColumn, $conditionsAndOptions);
    }

    static public function count(array $conditionsAndOptions = [], \Closure $configurator = null, $removeNotInnerJoins = false) {
        [, $conditionsAndOptions] = static::normalizeConditionsAndOptions([], $conditionsAndOptions);
        return parent::count($conditionsAndOptions, $configurator, $removeNotInnerJoins);
    }

    static public function selectOne($columns, array $conditionsAndOptions, ?\Closure $configurator = null) {
        [$columns, $conditionsAndOptions] = static::normalizeConditionsAndOptions((array)$columns, $conditionsAndOptions);
        return parent::selectOne($columns, $conditionsAndOptions, $configurator);
    }
    
    static public function selectOneAsDbRecord($columns, array $conditionsAndOptions, ?\Closure $configurator = null) {
        [$columns, $conditionsAndOptions] = static::normalizeConditionsAndOptions((array)$columns, $conditionsAndOptions);
        return parent::selectOneAsDbRecord($columns, $conditionsAndOptions, $configurator);
    }

    static public function expression($expression, array $conditionsAndOptions = []) {
        if (!($expression instanceof \PeskyORM\Core\DbExpr)) {
            $expression = new \PeskyORM\Core\DbExpr($expression);
        }
        [, $conditionsAndOptions] = static::normalizeConditionsAndOptions([], $conditionsAndOptions);
        return static::selectValue($expression, $conditionsAndOptions);
    }
    
    /**
     * @deprecated
     */
    static public function exists($conditionsAndOptions) {
        $conditionsAndOptions['LIMIT'] = 1;
        return (int)static::expression('1', $conditionsAndOptions) === 1;
    }
    
    /**
     * @deprecated
     */
    static public function sum($column, $conditionsAndOptions = null) {
        return 0 + static::expression("SUM(`$column`)", $conditionsAndOptions);
    }
    
    /**
     * @deprecated
     */
    static public function max($column, $conditionsAndOptions = null) {
        return 0 + static::expression("MAX(`$column`)", $conditionsAndOptions);
    }
    
    /**
     * @deprecated
     */
    static public function min($column, $conditionsAndOptions = null) {
        return 0 + static::expression("MIN(`$column`)", $conditionsAndOptions);
    }
    
    /**
     * @deprecated
     */
    static public function avg($column, $conditionsAndOptions = null) {
        return 0 + static::expression("AVG(`$column`)", $conditionsAndOptions);
    }
    
    /**
     * @deprecated
     * @param bool $readOnly
     * @param null $transactionType
     */
    static public function begin($readOnly = false, $transactionType = null) {
        static::beginTransaction($readOnly, $transactionType);
    }
    
    /**
     * @deprecated
     */
    static public function commit() {
        static::commitTransaction();
    }
    
    /**
     * @deprecated
     */
    static public function rollback() {
        static::rollBackTransaction();
    }

}
