<?php

namespace PeskyORM\Core;

use Swayok\Utils\StringUtils;
use Swayok\Utils\ValidateValue;

class DbSelect {

    /**
     * Main table name to select data from
     * @var string
     */
    protected $tableName;
    /**
     * @var string
     */
    protected $dbSchema;
    /**
     * @var string
     */
    protected $tableAlias;
    /**
     * @var DbAdapterInterface
     */
    protected $connection;
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
     * @var DbExpr
     */
    protected $having = '';
    /**
     * @var int
     */
    protected $limit = 0;
    /**
     * @var int
     */
    protected $offset = 0;
    /**
     * @var DbJoinConfig[]
     */
    protected $joins = [];

    /**
     * @param string $tableName
     * @param DbAdapterInterface $connection
     * @return $this
     * @throws \InvalidArgumentException
     */
    static public function from($tableName, DbAdapterInterface $connection) {
        return new static($tableName, $connection);
    }

    /**
     * @param string $tableName - table name or DbTable object
     * @param DbAdapterInterface $connection
     * @throws \InvalidArgumentException
     */
    public function __construct($tableName, DbAdapterInterface $connection) {
        if (!is_string($tableName) || empty($tableName)) {
            throw new \InvalidArgumentException('$tableName argument must be a not-empty string');
        }
        $this->tableName = $tableName;
        $this->tableAlias = StringUtils::classify($this->getTableAlias());
        $this->adapter = $connection;
        $this->columns([]);
    }

    /**
     * @param string $schema
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setDbSchemaName($schema) {
        if (!is_string($schema) || empty($schema)) {
            throw new \InvalidArgumentException('$schema argument must be a not-empty string');
        }
        $this->dbSchema = $schema;
        return $this;
    }

    /**
     * @return string
     */
    public function getTableSchema() {
        return $this->dbSchema;
    }

    /**
     * @return string
     */
    public function getTableName() {
        return $this->tableName;
    }

    /**
     * @return string
     */
    public function getTableAlias() {
        return $this->tableAlias;
    }

    /**
     * @return DbAdapterInterface
     */
    public function getConnection() {
        return $this->connection;
    }

    /**
     * Build query from passed array
     * @param array $conditionsAndOptions - list of conditions and special keys:
     *      'COLUMNS' - list of columns to select, array or '*'
     *      'ORDER' - ORDER BY, array ['col1_name' => 'desc', 'col2_name', DbExpr::create('RAND()')]
     *      'GROUP' - GROUP BY, array ['col1_name', DbExpr::create('expression')]
     *      'LIMIT' - int >= 0; 0 - unlimited
     *      'OFFSET' - int >= 0
     *      'HAVING' - DbExpr,
     *      'JOINS' - array of DbJoinConfig
     * @return $this
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function parseArray(array $conditionsAndOptions) {
        $conditionsAndOptions = $this->normalizeConditionsAndOptionsArray($conditionsAndOptions);
        // COLUMNS
        if (!empty($conditionsAndOptions['COLUMNS'])) {
            if (!is_array($conditionsAndOptions['COLUMNS']) && $conditionsAndOptions['COLUMNS'] !== '*') {
                throw new \InvalidArgumentException(
                    'COLUMNS key in $conditionsAndOptions argument must be an array or \'*\''
                );
            }
            if ($conditionsAndOptions['COLUMNS'] === '*') {
                $this->columns([]);
            } else {
                $this->columns($conditionsAndOptions['COLUMNS']);
            }
        }
        // ORDER BY
        if (!empty($conditionsAndOptions['ORDER'])) {
            if (!is_array($conditionsAndOptions['ORDER'])) {
                throw new \InvalidArgumentException('ORDER key in $conditionsAndOptions argument must be an array');
            }
            foreach ($conditionsAndOptions['ORDER'] as $columnName => $direction) {
                if ($direction instanceof DbExpr || is_int($columnName)) {
                    $this->orderBy($direction);
                } else if (!in_array(strtolower($direction), ['asc', 'desc'], true)) {
                    throw new \InvalidArgumentException(
                        "ORDER key contains invalid direction '{$direction}' for a column '{$columnName}'. "
                            . "'ASC' or 'DESC' expected"
                    );
                } else {
                    $this->orderBy($columnName, strtolower($direction) === 'asc');
                }
            }
        }
        // LIMIT
        if (!empty($conditionsAndOptions['LIMIT'])) {
            if (!ValidateValue::isInteger($conditionsAndOptions['LIMIT']) || (int)$conditionsAndOptions['LIMIT'] < 0) {
                throw new \InvalidArgumentException(
                    'LIMIT key in $conditionsAndOptions argument must be an integer >= 0'
                );
            }
            $this->limit($conditionsAndOptions['LIMIT']);
        }
        // OFFSET
        if (!empty($conditionsAndOptions['OFFSET'])) {
            if (!ValidateValue::isInteger($conditionsAndOptions['OFFSET']) || (int)$conditionsAndOptions['OFFSET'] < 0) {
                throw new \InvalidArgumentException(
                    'OFFSET key in $conditionsAndOptions argument must be an integer >= 0'
                );
            }
            $this->limit($conditionsAndOptions['OFFSET']);
        }
        // GROUP BY
        if (!empty($conditionsAndOptions['GROUP'])) {
            if (!is_array($conditionsAndOptions['GROUP'])) {
                throw new \InvalidArgumentException('GROUP key in $conditionsAndOptions argument must be an array');
            }
            $this->groupBy($conditionsAndOptions['GROUP']);
        }
        // HAVING
        if (!empty($conditionsAndOptions['HAVING'])) {
            if (!($conditionsAndOptions['HAVING'] instanceof DbExpr)) {
                throw new \InvalidArgumentException(
                    'HAVING key in $conditionsAndOptions argument must be an instance of DbExpr class'
                );
            }
            $this->having($conditionsAndOptions['HAVING']);
        }
        // JOINS
        if (!empty($conditionsAndOptions['JOINS'])) {
            if (!is_array($conditionsAndOptions['JOINS'])) {
                throw new \InvalidArgumentException('JOINS key in $conditionsAndOptions argument must be an array');
            }
            foreach ($conditionsAndOptions['JOINS'] as $join) {
                $this->join($join);
            }
        }
        // CONDITIONS
        $conditions = array_diff($conditionsAndOptions, array_flip($this->getListOfSpecialKeysInConditionsAndOptions()));
        if (!empty($conditions)) {
            $this->where($conditions);
        }
        return $this;
    }

    /**
     * @return array
     * @throws \LengthException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     * @throws \BadMethodCallException
     */
    public function fetchOne() {
        return $this->_fetch(Utils::FETCH_FIRST);
    }

    /**
     * @return array
     * @throws \LengthException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    public function fetchMany() {
        return $this->_fetch(Utils::FETCH_ALL);
    }

    /**
     * @return array
     * @throws \LengthException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function fetchNextPage() {
        if (!$this->limit) {
            throw new \BadMethodCallException('It is impossible to use pagination when there is not limit');
        }
        $this->offset($this->offset + $this->limit);
        return $this->_fetch(Utils::FETCH_ALL);
    }

    /**
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    public function fetchCount($ignoreLeftJoins = true) {
        return (int) $this->getConnection()->query($this->getCountQuery($ignoreLeftJoins), Utils::FETCH_VALUE);
    }

    /**
     * @return array
     * @throws \LengthException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    public function fetchColumn() {
        return $this->_fetch(Utils::FETCH_COLUMN);
    }

    /**
     * @param string $keysColumn
     * @param string $valuesColumn
     * @return array
     * @throws \LengthException
     * @throws \PDOException
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
     * @throws \LengthException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    public function fetchValue(DbExpr $expression) {
        return $this->columns([$expression])->_fetch(Utils::FETCH_VALUE);
    }

    /**
     * @param string $selectionType - one of PeskyORM\Core\Utils::FETCH_*
     * @return array|string
     * @throws \LengthException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    protected function _fetch($selectionType) {
        $data = $this->getConnection()->query($this->getQuery(), $selectionType);
        if (in_array($selectionType, [Utils::FETCH_COLUMN, Utils::FETCH_VALUE], true)) {
            return $data;
        } else if ($selectionType === Utils::FETCH_FIRST) {
            return $this->normalizeRecord($data);
        } else {
            $records = [];
            foreach ($data as $record) {
                $records[] = $this->normalizeRecord($record);
            }
            return $records;
        }
    }

    /**
     * @return string
     * @throws \LengthException
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    public function getQuery() {
        $table = $this->makeTableNameWithAliasForQuery(
            $this->getTableName(),
            $this->getTableAlias(),
            $this->getTableSchema()
        );
        $columns = $this->makeColumnsForQuery();
        $conditions = $this->makeConditions();
        $joins = $this->makeJoins(false);
        $group = $this->makeGroupBy();
        $order = $this->makeOrderBy();
        $limit = $this->makeLimit();
        $offset = $this->makeOffset();
        $having = $this->makeHaving();
        $query = "SELECT {$columns} FROM {$table} {$joins} {$conditions} {$group} {$having} {$order} {$limit} {$offset}";
        return $query;
    }

    /**
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return string
     * @throws \InvalidArgumentException
     * @throws \PDOException
     */
    public function getCountQuery($ignoreLeftJoins = true) {
        $table = $this->makeTableNameWithAliasForQuery(
            $this->getTableName(),
            $this->getTableAlias(),
            $this->getTableSchema()
        );
        $conditions = $this->makeConditions();
        $joins = $this->makeJoins($ignoreLeftJoins);
        $group = $this->makeGroupBy();
        $having = $this->makeHaving();
        $query = "SELECT `COUNT(*)` FROM {$table} {$joins} {$conditions} {$group} {$having}";
        return $query;
    }

    /**
     * @param array $columns -
     *  - array === []: all columns
     *  - array === ['*']: all columns
     *  - array format: [
     *      'col1Name',
     *      'TableAlias.col2name',
     *      'alias1' => DbExpr::create('Count(*)'), //< converted to DbExpr::create('Count(*) as `alias1`'),
     *      'alias2' => 'col4', //< converted to DbExpr::create('`col4` as `alias2`')
     *   ]
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
     * @param string|DbExpr $columnName - 'field1', 'JoinName.field1', DbExpr::create('RAND()')
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
            $this->orderBy[] = $columnName;
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
     * @param array $columns - can contain 'col1' and 'ModelAlias.col1', DbExpr::create('expression')
     * @param bool $append - true: append to existing groups | false: replace existsing groups
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function groupBy(array $columns, $append = true) {
        if (!$append) {
            $this->groupBy = [];
        }
        foreach ($columns as $index => $columnName) {
            if ($columnName instanceof DbExpr) {
                $this->groupBy[] = $columnName;
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
     * @param DbExpr $expression
     * @return $this
     */
    public function having(DbExpr $expression) {
        $this->having = $expression;
        return $this;
    }

    /**
     * @param DbJoinConfig $joinConfig
     * @return $this
     * @throws \BadMethodCallException
     */
    public function join(DbJoinConfig $joinConfig) {
        if (array_key_exists($joinConfig->getJoinName(), $this->joins)) {
            throw new \BadMethodCallException("Join with name '{$joinConfig->getJoinName()}' already defined");
        }
        $this->joins[$joinConfig->getJoinName()] = $joinConfig;
        return $this;
    }

    /* ------------------------------------> SERVICE METHODS <-----------------------------------> */

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
     *  3. 'JoinName.column3' => [
     *    'name' => 'column3',
     *    'alias' => $this->makeColumnAlias($alias ?: 'column3'),
     *    'realtion' => 'JoinName',
     *    'typecast' => null,
     *  ]
     *  4. 'JoinName.column4::varchar' => [
     *    'name' => 'column4',
     *    'alias' => $this->makeColumnAlias($alias ?: 'column4'),
     *    'realtion' => 'JoinName',
     *    'typecast' => 'varchar',
     *  ]
     * @param string $columnName
     * @param string|null $columnAlias
     * @param string|null $joinName
     * @return array - contains keys: 'name', 'alias', 'joinName', 'typecast'. All keys are strings
     * @throws \InvalidArgumentException
     */
    protected function analyzeColumnName($columnName, $columnAlias = null, $joinName = null) {
        $typeCast = null;
        if (!is_string($columnName)) {
            throw new \InvalidArgumentException('$columnName argument must be a string');
        }
        if ($columnAlias !== null && !is_string($columnAlias)) {
            throw new \InvalidArgumentException('$alias argument must be a string or null');
        }
        if ($joinName !== null && !is_string($joinName)) {
            throw new \InvalidArgumentException('$joinName argument must be a string or null');
        }
        $columnName = trim($columnName);
        if (preg_match('%^(.*?)\s+AS\s+(.+)$%is', $columnName, $aliasMatches)) {
            // 'col1 as alias1' or 'JoinName.col2 AS alias2' or 'JoinName.col3::datatype As alias3'
            if (!$columnAlias) {
                $columnAlias = $aliasMatches[2];
            }
            $columnName = $aliasMatches[1];
        }
        if (preg_match('%^(.*?)::([a-zA-Z0-9 _]+)$%is', $columnName, $dataTypeMatches)) {
            // 'col1::datatype' or 'JoinName.col2::datatype'
            $columnName = $aliasMatches[1];
            $typeCast = trim($aliasMatches[2]);
        }
        if (preg_match('%^(\w+)\.(\w+)$%i', trim($columnName), $columnParts)) {
            // JoinName.column or JoinName.column::datatype or
            list(, $columnName, $joinName) = $columnParts;
        }
        $columnAlias = $this->makeColumnAlias($columnAlias ?: $columnName, $joinName);
        $joinAlias = $joinName ? $this->getShortAlias($joinName) : null;
        return [
            'name' => $columnName,
            'alias' => $columnAlias,
            'join_name' => $joinName,
            'join_alias' => $joinAlias,
            'type_cast' => $typeCast
        ];
    }

    /**
     * @param string $columnName
     * @param null|string $tableAlias
     * @return string
     */
    protected function makeColumnAlias($columnName, $tableAlias = null) {
        return '_' . $this->getShortAlias($tableAlias ?: $this->getTableAlias()) . '__' . $columnName;
    }

    /**
     * @param array $columnInfo - return of $this->analyzeColumnName($columnName)
     * @return string - something like: `JoinAlias`.`column_name`::typecast as `ColumnAlias`
     */
    protected function makeColumnNameWithAliasForQuery(array $columnInfo) {
        $tableAlias = $columnInfo['join_alias'] ?: $this->getShortAlias($this->getTableAlias());
        $columnName = "`{$tableAlias}`.`{$columnInfo['name']}`";
        if ($columnInfo['type_cast']) {
            $columnName = $this->getConnection()->addDataTypeCastToExpression($columnInfo['type_cast'], $columnName);
        }
        return "{$columnName} AS `{$columnInfo['alias']}`";
    }

    /**
     * @param string $tableName
     * @param string $tableAlias
     * @param string|null $tableSchema
     * @return string
     */
    protected function makeTableNameWithAliasForQuery($tableName, $tableAlias, $tableSchema = null) {
        $schema = '';
        if ($this->getConnection()->isDbSupportsTableSchemas()) {
            $schema = '`' . ($tableSchema ?: $this->getConnection()->getDefaultTableSchema()) . '`.';
        }
        return $schema . "`{$tableName}` AS `{$this->getShortAlias($tableAlias)}`";
    }

    /**
     * @param array $columnInfo - return of $this->analyzeColumnName($columnName)
     * @return string `TableAlias`.`column_name`::typecast
     */
    protected function makeColumnNameForCondition(array $columnInfo) {
        $tableAlias = $columnInfo['join_alias'] ?: $this->getTableAlias();
        $columnName = "`{$this->getShortAlias($tableAlias)}`.`{$columnInfo['name']}`";
        if ($columnInfo['type_cast']) {
            return $this->getConnection()->addDataTypeCastToExpression($columnInfo['type_cast'], $columnName);
        } else {
            return $columnName;
        }
    }

    /**
     * Add columns into options and resolve contains
     * @param mixed $options
     * @return array|mixed
     */
    protected function normalizeConditionsAndOptionsArray($options) {
        if (!is_array($options)) {
            $options = (!empty($options) && is_string($options)) ? [$options] : [];
        }
        if (array_key_exists('JOIN', $options)) {
            $options['JOINS'] = $options['JOIN'];
            unset($options['JOIN']);
        }
        return $options;
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
     * @param null $joinName
     * @return array
     * @throws \InvalidArgumentException
     */
    protected function normalizeColumnsList(array $columns, $joinName = null) {
        if (empty($columns) || $columns === ['*']) {
            $alias = $this->getShortAlias([$joinName ?: $this->getTableAlias()]);
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
                        $columnAlias = $this->makeColumnAlias($columnAlias, $joinName);
                        $columnName = $columnName->get() . " AS `{$columnAlias}`";
                    }
                    $normalizedColumns[] = $columnName;
                } else if (is_string($columnName)) {
                    $normalizedColumns[] = $this->analyzeColumnName(
                        $columnName,
                        is_int($columnAlias) ? null : $columnAlias,
                        $joinName
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

    /**
     * @return array
     */
    protected function getListOfSpecialKeysInConditionsAndOptions() {
        return ['COLUMNS', 'LIMIT', 'OFFSET', 'HAVING', 'GROUP', 'ORDER', 'JOINS'];
    }

    /**
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function makeConditions() {
        $conditions = Utils::assembleWhereConditionsFromArray(
            $this->getConnection(),
            $this->where,
            'AND',
            function ($columnName) {
                if ($columnName instanceof DbExpr) {
                    return $columnName->get();
                } else {
                    $columnInfo = $this->analyzeColumnName($columnName);
                    return $this->getConnection()->quoteName($this->makeColumnNameForCondition($columnInfo));
                }
            }
        );
        $conditions = trim($conditions);
        return empty($conditions) ? '' : 'WHERE ' . $conditions;
    }

    /**
     * @return string
     */
    protected function makeHaving() {
        return 'HAVING ' . $this->having->get();
    }

    /**
     * @return string
     */
    protected function makeGroupBy() {
        $groups = [];
        foreach ($this->groupBy as $column) {
            if ($column instanceof DbExpr) {
                $groups[] = $column->get();
            } else {
                $groups[] = $this->makeColumnNameForCondition($column);
            }
        }
        return count($groups) ? 'GROUP BY ' . implode(', ', $groups) : '';
    }

    /**
     * @return string
     */
    protected function makeOrderBy() {
        $orders = [];
        foreach ($this->orderBy as $columnInfo) {
            if ($columnInfo instanceof DbExpr) {
                $orders[] = $columnInfo->get();
            } else {
                $orders[] = $this->makeColumnNameForCondition($columnInfo) . ' ' . $columnInfo['direction'];
            }
        }
        return count($orders) ? 'ORDER BY ' . implode(', ', $orders) : '';
    }

    /**
     * @param bool $ignoreLeftJoins
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function makeJoins($ignoreLeftJoins = false) {
        $joins = [];
        foreach ($this->joins as $joinConfig) {
            if ($ignoreLeftJoins && $joinConfig->getJoinType() === $joinConfig::JOIN_LEFT) {
                continue;
            }
            $type = strtoupper($joinConfig->getJoinType()) . ' JOIN';
            $table = $this->makeTableNameWithAliasForQuery(
                $joinConfig->getForeignTableName(),
                $joinConfig->getJoinName(),
                $joinConfig->getForeignTableSchema()
            );
            $conditions = $this->makeJoinConditions($joinConfig);
            $joins[] = "$type $table ON ($conditions)";
        }
        return implode(' ', $joins);
    }

    /**
     * @param DbJoinConfig $joinConfig
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    protected function makeJoinConditions(DbJoinConfig $joinConfig) {
        $conditions = array_merge(
            [
                "{$joinConfig->getTableAlias()}.{$joinConfig->getTableName()}"
                    => "{$joinConfig->getJoinName()}.{$joinConfig->getForeignColumnName()}"
            ],
            $joinConfig->getAdditionalJoinConditions()
        );
        return Utils::assembleWhereConditionsFromArray(
            $this->getConnection(),
            $conditions,
            'AND',
            function ($columnName) {
                if ($columnName instanceof DbExpr) {
                    return $columnName->get();
                } else {
                    $columnInfo = $this->analyzeColumnName($columnName);
                    return $this->getConnection()->quoteName($this->makeColumnNameForCondition($columnInfo));
                }
            }
        );
    }

    /**
     * @return string
     * @throws \InvalidArgumentException
     * @throws \LengthException
     */
    protected function makeColumnsForQuery() {
        $columns = [];
        foreach ($this->columns as $columnInfo) {
            if (is_string($columnInfo)) {
                $columns[] = $columnInfo;
            } else {
                $columns[] = $this->makeColumnNameWithAliasForQuery($columnInfo);
            }
        }
        foreach ($this->joins as $joinConfig) {
            $joinColumns = $this->normalizeColumnsList(
                $joinConfig->getForeignColumnsToSelect(),
                $joinConfig->getJoinName()
            );
            foreach ($joinColumns as $columnInfo) {
                if (is_string($columnInfo)) {
                    $columns[] = $columnInfo;
                } else {
                    $columns[] = $this->makeColumnNameWithAliasForQuery($columnInfo);
                }
            }
        }
        if (empty($columns)) {
            throw new \LengthException('There is no columns to select');
        }
        return implode(',', $columns);
    }

    /**
     * @return string
     */
    protected function makeLimit() {
        return $this->limit > 0 ? 'LIMIT ' . $this->limit : '';
    }

    /**
     * @return string
     */
    protected function makeOffset() {
        return $this->offset > 0 ? 'OFFSET ' . $this->offset : '';
    }

    /**
     * Convert key-value array received from DB to nested array with joins data stored under join names inside
     * main array. Also decodes columns aliases (keys in original array)
     * @param array $record
     * @return array - ['col1' => 'val1', 'col2' => 'val2', 'Join1Name' => ['jcol1' => 'jvalue1', ...], ...]
     */
    private function normalizeRecord(array $record) {
        $dataBlocks = [];
        // process record's column aliases and group column values by table alias
        $shortAliasToAlias = array_flip($this->shortAliases);
        foreach ($record as $columnAlias => $value) {
            if (preg_match('%^_(.+?)__(.+?)$%', $columnAlias, $colInfo)) {
                list(, $tableAlias, $column) = $colInfo;
                if (array_key_exists($tableAlias, $shortAliasToAlias)) {
                    $tableAlias = $shortAliasToAlias[$tableAlias];
                }
                if (empty($dataBlocks[$tableAlias])) {
                    $dataBlocks[$tableAlias] = [];
                }
                $dataBlocks[$tableAlias][$column] = $value;
            } else {
                $dataBlocks[$columnAlias] = $value;
            }
        }
        // make record nested + add missing child records
        $nested = array_key_exists($this->getTableAlias(), $dataBlocks) ? $dataBlocks[$this->getTableAlias()] : [];
        foreach ($this->joins as $joinConfig) {
            if (!empty($dataBlocks[$joinConfig->getJoinName()])) {
                $nested[$joinConfig->getJoinName()] = $dataBlocks[$joinConfig->getJoinName()];
            } else {
                $nested[$joinConfig->getJoinName()] = [];
            }
        }
        return $nested;
    }


}