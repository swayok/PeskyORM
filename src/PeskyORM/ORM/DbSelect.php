<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use Swayok\Utils\StringUtils;
use Swayok\Utils\ValidateValue;

class DbSelect {

    /**
     * Main table name to select data from
     * @var string
     */
    protected $tableName;
    /**
     * @var DbTable
     */
    protected $table;
    /**
     * @var DbTableStructure
     */
    protected $tableStructure;
    /**
     * @var bool
     */
    protected $useOrm = true;
    protected $relations = [];
    /**
     * @var array
     */
    protected $shortAliases = [];
    /**
     * @var array
     */
    protected $columns = [];
    /**
     * @var boolean
     */
    protected $distinct = false;
    /**
     * @var array
     */
    protected $where = [];
    /**
     * @var array
     */
    protected $orderBy = [];
    /**
     * @var array
     */
    protected $groupBy = [];
    /**
     * @var int
     */
    protected $limit = 0;
    /**
     * @var int
     */
    protected $offset = 0;
    /**
     * @var array
     */
    protected $contains = [];
    /**
     * @var array
     */
    protected $joins = [];

    /**
     * @param string $tableName
     * @return $this
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function from($tableName) {
        return new static($tableName);
    }

    /**
     * @param string|DbTable $table - table name or DbTable object
     * @param bool $useOrm - enable/disable usage of ORM classes
     *      - true: if $table is string - DbTable object will be loaded from DbClassesManager, also there will be more
     *              validations related to DbTableStructure of a DbTable. No possiblity to use a table that has no
     *              DbTable and DbTableStructure instances
     *      - false: disables usage of ORM classes, less validations, but it becomes possible to use any table in DB
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function __construct($table, $useOrm = true) {
        $this->useOrm = $useOrm;
        if ($table instanceof DbTable) {
            $this->setTable($table);
        } else if (is_string($table)) {
            $this->tableName = $table;
            if ($this->useOrm) {
                $this->setTable(DbClassesManager::i()->getTableInstance($table));
            }
        } else {
            throw new \InvalidArgumentException('$table argument must be a string or instance of DbTable class');
        }
    }

    /**
     * Build query from passed array
     * @param array $conditionsAndOptions
     * @return $this
     */
    public function parseArray(array $conditionsAndOptions) {
        $conditionsAndOptions = $this->prepareSelect($conditionsAndOptions);
        // todo: config query from $conditionsAndOptions (don't forget about 'FIELDS' key)
        return $this;
    }

    /**
     * @return array
     */
    public function fetchOne() {
        // todo: protect from empty conditions
        return $this->_fetch(Utils::FETCH_FIRST);
    }

    /**
     * @return DbRecord
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function fetchOneAsDbRecord() {
        return DbClassesManager::i()->newRecord($this->tableName)->fromDbData($this->fetchOne());
    }

    /**
     * @return array
     */
    public function fetchMany() {
        return $this->_fetch(Utils::FETCH_ALL);
    }

    /**
     * @return array
     */
    public function fetchNextPage() {
        // todo: analyze LIMIT and OFFSET and update OFFSET to fetch next pack of data
        return $this->_fetch(Utils::FETCH_ALL);
    }

    /**
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     */
    public function fetchCount($ignoreLeftJoins = true) {
        return (int) $this->table->getConnection()->query($this->buildCountQuery($ignoreLeftJoins), Utils::FETCH_VALUE);
    }

    /**
     * @return array
     */
    public function fetchColumn() {
        return $this->_fetch(Utils::FETCH_COLUMN);
    }

    /**
     * @param string $keysColumn
     * @param string $valuesColumn
     * @return array
     * @throws \InvalidArgumentException
     */
    public function fetchAssoc($keysColumn, $valuesColumn) {
        $this->columns(['key' => $keysColumn, 'value' => $valuesColumn]);
        /** @var array $records */
        $records = $this->_fetch(Utils::FETCH_ALL);
        $assoc = [];
        foreach ($records as $data) {
            $assoc[$data[$keysColumn]] = $data[$valuesColumn];
        }
        return $assoc;
    }

    /**
     * @param DbExpr $expression
     * @return string
     * @throws \InvalidArgumentException
     */
    public function fetchValue(DbExpr $expression) {
        return $this->columns([$expression])
            ->_fetch(Utils::FETCH_VALUE);
    }

    /**
     * @param string $selectionType - one of PeskyORM\Core\Utils::FETCH_*
     * @return array|string
     */
    protected function _fetch($selectionType) {
        $data = $this->table->getConnection()->query($this->buildQuery(), $selectionType);
        return $data;
    }

    /**
     * @return string
     */
    public function buildQuery() {
        $query = 'SELECT ';
        return $query;
    }

    /**
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return string
     */
    public function buildCountQuery($ignoreLeftJoins = true) {
        // todo: remove ORDER, LIMIT, OFFSET, LEFT JOINs
        // todo: remove LEFT JOINs if $ignoreLeftJoins === true
        // todo: SELECT COUNT(*) FROM ....
        $query = 'SELECT ';
        return $query;
    }

    /**
     * @return string
     */
    public function getSelectionType() {
        return $this->selectionType;
    }

    /**
     * @return DbTable
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * @return DbTableStructure
     */
    public function getTableStructure() {
        return $this->tableStructure;
    }
    
    /**
     * Build valid 'JOIN' settings from 'CONTAIN' table aliases
     * @param array $conditionsAndOptions
     * @return mixed $where
     */
    public function resolveContains(array $conditionsAndOptions) {
        if (is_array($conditionsAndOptions)) {
            if (!empty($conditionsAndOptions['CONTAIN'])) {
                if (!is_array($conditionsAndOptions['CONTAIN'])) {
                    $conditionsAndOptions['CONTAIN'] = [$conditionsAndOptions['CONTAIN']];
                }
                $conditionsAndOptions['JOIN'] = [];

                foreach ($conditionsAndOptions['CONTAIN'] as $alias => $columns) {
                    if (is_int($alias)) {
                        $alias = $columns;
                        $columns = !empty($relation['fields']) ? $relation['fields'] : '*';
                    }
                    $relationConfig = $this->getTableRealtaion($alias);
                    if ($relationConfig->getType() === DbTableRelation::HAS_MANY) {
                        throw new DbModelException($this, "Queries with one-to-many joins are not allowed via 'CONTAIN' key");
                    } else {
                        $model = $this->getRelatedModel($alias);
                        $additionalConditions = $relationConfig->getAdditionalJoinConditions();
                        $joinType = $relationConfig->getJoinType();
                        if (is_array($columns)) {
                            if (isset($columns['TYPE'])) {
                                $joinType = $columns['TYPE'];
                            }
                            unset($columns['TYPE']);
                            if (isset($columns['CONDITIONS'])) {
                                $additionalConditions = array_replace_recursive($additionalConditions, $columns['CONDITIONS']);
                            }
                            unset($columns['CONDITIONS']);
                            if (!empty($columns['CONTAIN'])) {
                                $subContains = ['CONTAIN' => $columns['CONTAIN']];
                            }
                            unset($columns['CONTAIN']);
                            if (empty($columns)) {
                                $columns = '*';
                            }
                        }

                        $conditionsAndOptions['JOIN'][$alias] = DbJoinConfig::create($alias)
                            ->setConfigForLocalTable($this, $relationConfig->getColumn())
                            ->setJoinType($joinType)
                            ->setConfigForForeignTable($model, $relationConfig->getForeignColumn())
                            ->setAdditionalJoinConditions($additionalConditions)
                            ->setForeignColumnsToSelect($columns)
                            ->getConfigsForDbQuery();

                        if (!empty($subContains)) {
                            $subJoins = $model->resolveContains($subContains);
                            $conditionsAndOptions['JOIN'] = array_merge($conditionsAndOptions['JOIN'], $subJoins['JOIN']);
                        }
                    }
                }
                if (empty($conditionsAndOptions['JOIN'])) {
                    unset($conditionsAndOptions['JOIN']);
                }
            }
            unset($conditionsAndOptions['CONTAIN']);
        }
        return $conditionsAndOptions;
    }

    /**
     * @param array $columns - format: [
     *      'col1Name',
     *      'TableAlias.col2name',
     *      'alias1' => DbExpr::create('Count(*)'), //< converted to DbExpr::create('Count(*) as `alias1`'),
     *      'alias2' => 'col4', //< converted to DbExpr::create('`col4` as `alias2`')
     *  ]
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function columns(array $columns) {
        $this->columns[] = $this->normalizeColumnsList($columns);
        return $this;
    }

    /**
     * Set distinct flag to query (SELECT DISTINCT fields ...)
     * @param bool $value
     * @return $this
     */
    public function distinct($value = true) {
        $this->distinct = (bool)$value;
        return $this;
    }

    /**
     * Set Conditions
     * @param array $conditions
     * @return $this
     */
    public function where(array $conditions) {
        $this->where = $conditions;
        return $this;
    }

    /**
     * Add ORDER BY
     * @param string|DbExpr $columnName - 'field1', 'RelationName.field1', DbExpr::create('RAND()')
     * @param bool $isAscending - true: 'ASC'; false: 'DESC'; Ignore if $columnName instance of DbExpr
     * @param bool $append - true: append to existing orders | false: replace existsing orders
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function orderBy($columnName, $isAscending = true, $append = true) {
        if (empty($columnName)) {
            throw new \InvalidArgumentException('$columnName argument cannot be empty');
        }
        if (!is_string($columnName) && !($columnName instanceof DbExpr)) {
            throw new \InvalidArgumentException('$columnName argument must be a string or instance of DbExpr class');
        }
        if (!$append) {
            $this->orderBy = [];
        }
        if (!is_string($columnName)) {
            $this->orderBy[] = $columnName->get();
        } else {
            $this->orderBy[] = array_merge(
                $this->analyzeColumnName($columnName),
                ['direction' => $isAscending ? 'ASC' : 'DESC']
            );
        }
        return $this;
    }

    /**
     * Add GROUP BY
     * @param array $columns - can contain 'col1' and 'ModelAlias.col1'
     *      When ModelAlias omitted - $this->alias is used
     * @param bool $append - true: add $orderBy to existing grouping | false: replace existsing grouping
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function groupBy(array $columns, $append = true) {
        if (!$append) {
            $this->groupBy = [];
        }
        foreach ($columns as $index => $columnName) {
            if ($columnName instanceof DbExpr) {
                $this->groupBy[] = $columnName->get();
            } else if (is_string($columnName)){
                $this->groupBy[] = $this->analyzeColumnName($columnName);
            } else {
                throw new \InvalidArgumentException(
                    "$columns argument contains invalid value at index '$index'. "
                        . 'All values must be a strings or instances of DbExpr class'
                );
            }
        }
        return $this;
    }

    /**
     * Set LIMIT
     * @param int $limit - 0 = no limit;
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function limit($limit) {
        if (!ValidateValue::isInteger($limit)) {
            throw new \InvalidArgumentException('$limit argument must be an integer');
        } else if ($limit < 0) {
            throw new \InvalidArgumentException('$limit argument must be an integer value >= 0');
        }
        $this->limit = (int)$limit;
        return $this;
    }

    /**
     * Remove LIMIT
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function noLimit() {
        $this->limit(0);
        return $this;
    }

    /**
     * Set/Remove OFFSET
     * @param int $offset - 0 = no offset
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function offset($offset) {
        if (!ValidateValue::isInteger($offset)) {
            throw new \InvalidArgumentException('$offset argument must be an integer');
        } else if ($offset < 0) {
            throw new \InvalidArgumentException('$offset argument must be an integer value >= 0');
        }
        $this->offset = (int)$offset;
        return $this;
    }

    /**
     * Set LIMIT and OFFSET at once
     * @param int $limit - 0 = no limit;
     * @param int $offset - 0 = no offset
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function page($limit, $offset = 0) {
        return $this->limit($limit)->offset($offset);
    }

    /**
     * @param string $relationName
     * @param null|array $columns
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function contain($relationName, $columns = null, array $conditions = []) {
        if (!$this->useOrm) {
            throw new \BadMethodCallException('Action is impossible: DbSelect is not using ORM classes');
        }
        if ($columns !== null && !is_array($columns)) {
            throw new \InvalidArgumentException('$columns argument must be an array or null');
        }
        $columns = ($columns === null) ? [] : $this->normalizeColumnsList($columns);
        $this->contains[$relationName] = compact('columns', 'conditions');
    }

    /**
     * Add single join
     * @param DbJoinConfig $joinConfig
     * @return $this
     */
    public function join(DbJoinConfig $joinConfig) {
        if (empty($relatedAlias)) {
            $relatedAlias = $relatedModel->getAlias();
        }
        if (isset($this->aliasToTable[$relatedAlias])) {
            throw new DbQueryException($this, "DbQuery->join(): table alias [$relatedAlias] already used");
        } else if (isset($this->joins[$relatedAlias])) {
            throw new DbQueryException($this, "DbQuery->join(): table with alias [$relatedAlias] already joined");
        }
        if (empty($knownTableAlias)) {
            $knownTableAlias = $this->alias;
            if (empty($knownTableColumn)) {
                $knownTableColumn = $this->models[$this->table]->getPkColumnName();
            }
        } else if (empty($this->aliasToTable[$knownTableAlias])) {
            throw new DbQueryException($this, "DbQuery->join(): table with alias [$knownTableAlias] is not known");
        }
        if (empty($knownTableColumn)) {
            $knownTableColumn = $this->models[$this->aliasToTable[$knownTableAlias]]->getPkColumnName();
        }
        $this->models[$relatedModel->getTableName()] = $relatedModel;
        $this->mapAliasToTable($relatedAlias, $relatedModel->getTableName());
        $col1Info = $this->disassembleField($relatedColumn, $relatedAlias);
        $col2Info = $this->disassembleField($knownTableColumn, $knownTableAlias);
        $conditions = !empty($conditions) && is_array($conditions) ? $conditions : array();
        $conditions[] = $col1Info['assembled'] . '=' . $col2Info['assembled'];
        $this->joins[$relatedAlias] = array(
            'type' => in_array(strtolower(trim($type)), array('inner', 'left', 'right', 'full')) ? strtolower(trim($type)) : 'inner',
            'table' => $this->quoteName($this->getFullTableName($relatedModel)) . ' AS ' . $this->quoteName($relatedAlias),
            'on' => '(' . $this->assembleConditions($conditions) . ')',
        );
        $this->fields($fields, $relatedAlias);
        return $this;
    }

    // todo: add joinWithCustomConditions(DbModel  $relatedModel, $relatedAlias, $conditions, $fields = '*', $type = 'inner') when will be needed

    /* ------------------------------------> SERVICE METHODS <-----------------------------------> */

    /**
     * @param DbTable $table
     * @throws \BadMethodCallException
     */
    protected function setTable(DbTable $table) {
        $this->tableName = $table->getTableName();
        if ($this->useOrm) {
            $this->table = $table;
            $this->tableStructure = $table->getStructure();
            $this->getShortAlias($table->getAlias());
        } else {
            $this->getShortAlias(StringUtils::classify($this->tableName));
        }
    }

    /**
     * Analyze $columnName and return information about column
     * Examples:
     *  1. 'column1' => [
     *    'name' => 'column1',
     *    'alias' => $this->makeColumnAlias($alias ?: 'column1'),
     *    'realtion' => null,
     *    'typecast' => null,
     *  ]
     *  2.'column2 as alias1' => [
     *    'name' => 'column2',
     *    'alias' => $this->makeColumnAlias($alias ?: 'alias1'),
     *    'realtion' => null,
     *    'typecast' => null,
     *  ]
     *  3. 'RelationName.column3' => [
     *    'name' => 'column3',
     *    'alias' => $this->makeColumnAlias($alias ?: 'column3'),
     *    'realtion' => 'RelationName',
     *    'typecast' => null,
     *  ]
     *  4. 'RelationName.column4::varchar' => [
     *    'name' => 'column4',
     *    'alias' => $this->makeColumnAlias($alias ?: 'column4'),
     *    'realtion' => 'RelationName',
     *    'typecast' => 'varchar',
     *  ]
     * @param string $name
     * @param string|null $alias
     * @param string|null $relation
     * @return array - contains keys: 'name', 'alias', 'realtion', 'typecast'. All keys are strings
     * @throws \InvalidArgumentException
     */
    protected function analyzeColumnName($name, $alias = null, $relation = null) {
        $typecast = null;
        if (!is_string($name)) {
            throw new \InvalidArgumentException('$columnName argument must be a string');
        }
        if ($alias !== null && !is_string($alias)) {
            throw new \InvalidArgumentException('$alias argument must be a string or null');
        }
        if ($relation !== null && !is_string($relation)) {
            throw new \InvalidArgumentException('$relation argument must be a string or null');
        }
        $name = trim($name);
        if (preg_match('%^(.*?)\s+AS\s+(.+)$%is', $name, $aliasMatches)) {
            // 'col1 as alias1' or 'Relation.col2 AS alias2' or 'Relation.col3::datatype As alias3'
            if (!$alias) {
                $alias = $aliasMatches[2];
            }
            $name = $aliasMatches[1];
        }
        if (preg_match('%^(.*?)::([a-zA-Z0-9 _]+)$%is', $name, $dataTypeMatches)) {
            // 'col1::datatype' or 'Relation.col2::datatype'
            $name = $aliasMatches[1];
            $typecast = trim($aliasMatches[2]);
        }
        if (preg_match('%^(\w+)\.(\w+)$%i', trim($name), $columnParts)) {
            // Relation.column or Relation.column::datatype or
            list(, $name, $relation) = $columnParts;
        }
        $alias = $this->makeColumnAlias($alias ?: $name, $relation);
        return compact('name', 'alias', 'relation', 'typecast');
    }

    /**
     * @param string $columnName
     * @param null|string $tableAlias
     * @return string
     */
    protected function makeColumnAlias($columnName, $tableAlias = null) {
        if (!$tableAlias) {
            $tableAlias = $this->getTable()->getAlias();
        }
        return '_' . $this->shortAliases[$tableAlias] . '__' . $columnName;
    }

    protected function makeColumnNameFromColumnInfo(array $columnInfo, $addAlias = true) {

    }

    /**
     * Add columns into options and resolve contains
     * @param mixed $options
     * @return array|mixed
     */
    protected function prepareSelect($options) {
        if (!is_array($options)) {
            $options = (!empty($options) && is_string($options)) ? [$options] : [];
        } else {
            $options = $this->resolveContains($options);
        }
        return $options;
    }

    protected function addRelation(DbTableRelation $relation) {
        if (!array_key_exists($relation->getName(), $this->relations)) {
            throw new \InvalidArgumentException("Relation with name '{$relation->getName()}' already defined");
        }
        $this->relations[$relation->getName()] = $relation;
        $this->getShortAlias($relation->getName());
    }

    /**
     * @param string $alias
     * @return string
     */
    protected function getShortAlias($alias) {
        if (!array_key_exists($alias, $this->shortAliases)) {
            $this->shortAliases[$alias] = mb_strlen($alias) > 16
                ? chr(mt_rand(97, 122)) . hash('crc32b', $alias) . mt_rand(0, 9)
                : $alias;
        }
        return $this->shortAliases[$alias];
    }

    /**
     * @param array $columns
     * @param null $relationName
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function normalizeColumnsList(array $columns, $relationName = null) {
        if (empty($columns)) {
            $alias = $this->getShortAlias([$relationName ?: $this->getTable()->getAlias()]);
            return ["`$alias`.`*`"];
        } else {
            $normalizedColumns = [];
            foreach ($columns as $columnAlias => $columnName) {
                if (empty($columnName)) {
                    throw new \InvalidArgumentException(
                        "\$columns argument contains an empty column name for a key '$columnAlias'"
                    );
                }
                if ($columnName instanceof DbExpr) {
                    if (!is_int($columnAlias)) {
                        $columnAlias = $this->makeColumnAlias($columnAlias, $relationName);
                        $columnName = $columnName->get() . " AS `{$columnAlias}`";
                    }
                    $normalizedColumns[] = $columnName;
                } else if (is_string($columnName)) {
                    $normalizedColumns[] = $this->analyzeColumnName(
                        $columnName,
                        is_int($columnAlias) ? null : $columnAlias,
                        $relationName
                    );
                } else {
                    throw new \InvalidArgumentException(
                        "Invalid column name for a key '$columnAlias'. "
                            . '$columns argument must contain only strings and instances of DbExpr class.'
                    );
                }
            }
        }
        return $normalizedColumns;
    }


}