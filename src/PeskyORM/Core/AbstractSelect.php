<?php

namespace PeskyORM\Core;

use PeskyORM\ORM\OrmJoinInfo;
use Swayok\Utils\ValidateValue;

/**
 * @method join(AbstractJoinInfo $joinInfo, bool $append = true) //< it is impossible to overwrite method to use child class instead of AbstractJoinInfo
 */
abstract class AbstractSelect {

    /**
     * @var array - key = full table alias or join name; value - short alias
     */
    protected $shortJoinAliases = [];
    /**
     * @var array - key = full column alias or name; value - short column alias
     */
    protected $shortColumnAliases = [];
    /**
     * @var array
     */
    protected $columns = [];
    /**
     * @var array
     */
    protected $columnsRaw = ['*'];
    /**
     * @var boolean
     */
    protected $distinct = false;
    /**
     * @var array
     */
    protected $distinctColumns = [];
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
     * @var array
     */
    protected $having = [];
    /**
     * @var AbstractSelect[]
     */
    protected $with = [];
    /**
     * @var int
     */
    protected $limit = 0;
    /**
     * @var int
     */
    protected $offset = 0;
    /**
     * @var JoinInfo[]
     */
    protected $joins = [];
    /**
     * List of JOINs names that are mentioned in WHERE and HAVING conditions.
     * This is used in simplified query builder so it won't drop LEFT JOINs if
     * they are required for query to be successful
     * @var array
     */
    protected $joinsUsedInWhereAndHavingConditions = [];
    /**
     * @var array
     */
    protected $analyzedColumns = [];
    /**
     * Indicates that Select has changed since last getQuery or getSimplifiedQuery call
     * @var null|array - null: all dirty | array - only some items are dirty
     */
    protected $isDirty;
    /**
     * @var array
     */
    protected $columnAliasToColumnInfo = [];

    /**
     * @return string
     */
    abstract public function getTableName(): string;

    /**
     * @return string
     */
    abstract public function getTableAlias(): string;

    /**
     * @return string|null
     */
    abstract public function getTableSchemaName(): ?string;

    /**
     * @return DbAdapterInterface
     */
    abstract public function getConnection(): DbAdapterInterface;

    /**
     * Build query from passed array
     * @param array $conditionsAndOptions - list of conditions and special keys:
     *      'COLUMNS' - list of columns to select, array or '*'
     *      'ORDER' - ORDER BY, array ['col1_name' => 'desc', 'col2_name', DbExpr::create('RAND()')]
     *      'GROUP' - GROUP BY, array ['col1_name', DbExpr::create('expression')]
     *      'LIMIT' - int >= 0; 0 - unlimited
     *      'OFFSET' - int >= 0
     *      'HAVING' - DbExpr,
     *      'JOINS' - array of JoinInfo
     * @return $this
     */
    public function fromConfigsArray(array $conditionsAndOptions) {
        $conditionsAndOptions = $this->normalizeConditionsAndOptionsArray($conditionsAndOptions);
        $this->parseNormalizedConfigsArray($conditionsAndOptions);
        return $this;
    }

    /**
     * @param array $conditionsAndOptions
     * @throws \InvalidArgumentException
     */
    protected function parseNormalizedConfigsArray(array $conditionsAndOptions) {
        // WITH
        if (!empty($conditionsAndOptions['WITH'])) {
            if (!is_array($conditionsAndOptions['WITH'])) {
                throw new \InvalidArgumentException(
                    'WITH key in $conditionsAndOptions argument must a key-value array where key is entity name and value is an instance of AbstractSelect class'
                );
            }
            foreach ($conditionsAndOptions['WITH'] as $selectAlias => $select) {
                if (!($select instanceof self)) {
                    throw new \InvalidArgumentException(
                        "WITH key in \$conditionsAndOptions argument contains invalid value for key {$selectAlias}. Value must be an instance of AbstractSelect class"
                    );
                } else if (!$this->getConnection()->isValidDbEntityName($selectAlias, false)) {
                    throw new \InvalidArgumentException(
                        "WITH key in \$conditionsAndOptions argument contains invalid key {$selectAlias}. Key must be a string that fits DB entity naming rules (usually alphanumeric string with underscores)"
                    );
                }
                $this->with($select, $selectAlias);
            }
        }
        // JOINS - must be 1st to allow columns validation in OrmSelect or other child class
        if (!empty($conditionsAndOptions['JOINS'])) {
            if (!is_array($conditionsAndOptions['JOINS'])) {
                throw new \InvalidArgumentException('JOINS key in $conditionsAndOptions argument must be an array');
            }
            foreach ($conditionsAndOptions['JOINS'] as $join) {
                if (!($join instanceof AbstractJoinInfo)) {
                    throw new \InvalidArgumentException(
                        'JOINS key in $conditionsAndOptions argument must contain only instances of JoinInfo class'
                    );
                }
                $this->join($join);
            }
        }
        // DISTINCT
        if (!empty($conditionsAndOptions['DISTINCT'])) {
            $this->distinct(
                true,
                is_array($conditionsAndOptions['DISTINCT']) ? $conditionsAndOptions['DISTINCT'] : null
            );
        }
        // ORDER BY
        if (!empty($conditionsAndOptions['ORDER'])) {
            if (!is_array($conditionsAndOptions['ORDER'])) {
                throw new \InvalidArgumentException('ORDER key in $conditionsAndOptions argument must be an array');
            }
            foreach ($conditionsAndOptions['ORDER'] as $columnName => $direction) {
                if ($direction instanceof DbExpr || is_int($columnName)) {
                    $this->orderBy($direction);
                } else if (!preg_match('%^(asc|desc)(\s*(nulls)\s*(first|last))?$%i', $direction)) {
                    throw new \InvalidArgumentException(
                        "ORDER key contains invalid direction '{$direction}' for a column '{$columnName}'. "
                            . "'ASC' or 'DESC' with optional 'NULLS FIRST' or 'NULLS LAST' expected"
                    );
                } else {
                    $this->orderBy($columnName, $direction);
                }
            }
        }
        // LIMIT
        if (!empty($conditionsAndOptions['LIMIT'])) {
            /** @noinspection NotOptimalIfConditionsInspection */
            if (!ValidateValue::isInteger($conditionsAndOptions['LIMIT']) || (int)$conditionsAndOptions['LIMIT'] < 0) {
                throw new \InvalidArgumentException(
                    'LIMIT key in $conditionsAndOptions argument must be an integer >= 0'
                );
            }
            $this->limit($conditionsAndOptions['LIMIT']);
        }
        // OFFSET
        if (!empty($conditionsAndOptions['OFFSET'])) {
            /** @noinspection NotOptimalIfConditionsInspection */
            if (!ValidateValue::isInteger($conditionsAndOptions['OFFSET']) || (int)$conditionsAndOptions['OFFSET'] < 0) {
                throw new \InvalidArgumentException(
                    'OFFSET key in $conditionsAndOptions argument must be an integer >= 0'
                );
            }
            $this->offset($conditionsAndOptions['OFFSET']);
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
            if (!is_array($conditionsAndOptions['HAVING'])) {
                throw new \InvalidArgumentException(
                    'HAVING key in $conditionsAndOptions argument must be an array like conditions'
                );
            }
            $this->having($conditionsAndOptions['HAVING']);
        }
        // CONDITIONS
        $conditions = array_diff_key($conditionsAndOptions, array_flip($this->getListOfSpecialKeysInConditionsAndOptions()));
        if (!empty($conditions)) {
            $this->where($conditions);
        }
    }

    protected function getListOfSpecialKeysInConditionsAndOptions(): array {
        return ['LIMIT', 'OFFSET', 'HAVING', 'GROUP', 'ORDER', 'JOINS', 'DISTINCT'];
    }

    /**
     * @return array
     */
    public function fetchOne(): array {
        return $this->_fetch(Utils::FETCH_FIRST);
    }

    /**
     * @return array
     */
    public function fetchMany(): array {
        return $this->_fetch(Utils::FETCH_ALL);
    }

    /**
     * @return array
     * @throws \BadMethodCallException
     */
    public function fetchNextPage(): array {
        if (!$this->limit) {
            throw new \BadMethodCallException('It is impossible to use pagination when there is no limit');
        }
        $this->offset($this->offset + $this->limit);
        return $this->_fetch(Utils::FETCH_ALL);
    }

    /**
     * @return array
     * @throws \BadMethodCallException
     */
    public function fetchPrevPage(): array {
        if (!$this->limit) {
            throw new \BadMethodCallException('It is impossible to use pagination when there is no limit');
        }
        $this->offset($this->offset - $this->limit);
        return $this->_fetch(Utils::FETCH_ALL);
    }

    /**
     * Count records matching provided conditions and options
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     */
    public function fetchCount(bool $ignoreLeftJoins = true): int {
        return (int)$this->getConnection()->query($this->getCountQuery($ignoreLeftJoins), Utils::FETCH_VALUE);
    }

    /**
     * Tests if there is at least 1 record matching provided conditions and options
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return bool
     */
    public function fetchExistence(bool $ignoreLeftJoins = true): bool {
        return (int)$this->getConnection()->query($this->getExistenceQuery($ignoreLeftJoins), Utils::FETCH_VALUE) === 1;
    }

    /**
     * @return array
     */
    public function fetchColumn(): array {
        return $this->_fetch(Utils::FETCH_COLUMN);
    }

    /**
     * @param string|DbExpr $keysColumn
     * @param string|DbExpr $valuesColumn
     * @return array
     */
    public function fetchAssoc($keysColumn, $valuesColumn): array {
        $this->columns(['key' => $keysColumn, 'value' => $valuesColumn]);
        /** @var array $records */
        $records = $this->_fetch(Utils::FETCH_ALL);
        $assoc = [];
        foreach ($records as $data) {
            $assoc[$data['key']] = $data['value'];
        }
        return $assoc;
    }

    /**
     * @param DbExpr $expression
     * @return string
     */
    public function fetchValue(DbExpr $expression): ?string {
        return $this->columns([$expression])->_fetch(Utils::FETCH_VALUE);
    }

    /**
     * @param string $selectionType - one of PeskyORM\Core\Utils::FETCH_*
     * @return mixed
     */
    protected function _fetch(string $selectionType) {
        $data = $this->getConnection()->query($this->getQuery(), $selectionType);
        if (in_array($selectionType, [Utils::FETCH_COLUMN, Utils::FETCH_VALUE], true)) {
            return $data;
        } else if ($selectionType === Utils::FETCH_FIRST) {
            $shortColumnAliasToAlias = array_flip($this->shortColumnAliases);
            $shortJoinAliasToAlias = array_flip($this->shortJoinAliases);
            return $this->normalizeRecord($data, $shortColumnAliasToAlias, $shortJoinAliasToAlias);
        } else {
            $records = [];
            $shortColumnAliasToAlias = array_flip($this->shortColumnAliases);
            $shortJoinAliasToAlias = array_flip($this->shortJoinAliases);
            foreach ($data as $record) {
                $records[] = $this->normalizeRecord($record, $shortColumnAliasToAlias, $shortJoinAliasToAlias);
            }
            return $records;
        }
    }

    public function getQuery(): string {
        $this->beforeQueryBuilding();
        $with = $this->makeWithQueries();
        $columns = $this->makeColumnsForQuery();
        $fromTableAndOthers = $this->buildQueryPartsAfterSelectColumns(false, true, true);
        $this->validateIfThereAreEnoughJoins();
        $this->notDirty();
        return "{$with}SELECT {$columns} {$fromTableAndOthers}";
    }

    /**
     * Make a simplified query without LIMIT, OFFSET and ORDER BY
     * Note: DISTINCT keyword is not applied!
     * @param string $expression - something like "COUNT(*)" or "1" to be selected by the query
     * @param bool $ignoreLeftJoins
     * @param bool $ignoreLimitAndOffset
     * @return string
     */
    protected function getSimplifiedQuery(string $expression, bool $ignoreLeftJoins = true, bool $ignoreLimitAndOffset = false): string {
        $this->beforeQueryBuilding();
        $with = $this->makeWithQueries();
        $fromTableAndOthers = $this->buildQueryPartsAfterSelectColumns($ignoreLeftJoins, false, !$ignoreLimitAndOffset);
        $this->validateIfThereAreEnoughJoins();
        $this->notDirty();
        return "{$with}SELECT $expression {$fromTableAndOthers}";
    }

    /**
     * @return string
     */
    public function buildQueryToBeUsedInWith(): string {
        $this->beforeQueryBuilding();
        $columns = $this->makeColumnsForQuery(true);
        $fromTableAndOthers = $this->buildQueryPartsAfterSelectColumns(false, true, true);
        $this->validateIfThereAreEnoughJoins();
        $this->notDirty();
        return "SELECT {$columns} {$fromTableAndOthers}";
    }

    /**
     * @param bool $ignoreLeftJoins
     * @param bool $withOrderBy
     * @param bool $withLimitAndOffset
     * @return string
     */
    protected function buildQueryPartsAfterSelectColumns(bool $ignoreLeftJoins, bool $withOrderBy, bool $withLimitAndOffset): string {
        $table = $this->makeTableNameWithAliasForQuery(
            $this->getTableName(),
            $this->getTableAlias(),
            $this->getTableSchemaName()
        );
        $groupBy = $this->makeGroupBy();
        $orderBy = $withOrderBy ? $this->makeOrderBy() : '';
        $limit = $withLimitAndOffset ? $this->makeLimit() : '';
        $offset = $withLimitAndOffset ? $this->makeOffset() : '';
        $conditions = $this->makeConditions($this->where, 'WHERE');
        $having = $this->makeConditions($this->having, 'HAVING');
        $joins = $this->makeJoins($ignoreLeftJoins);
        return "FROM {$table}{$joins}{$conditions}{$groupBy}{$having}{$orderBy}{$limit}{$offset}";
    }

    /**
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return string
     */
    public function getCountQuery(bool $ignoreLeftJoins = true): string {
        return $this->getSimplifiedQuery('COUNT(*)', $ignoreLeftJoins, true);
    }

    /**
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return string
     */
    public function getExistenceQuery(bool $ignoreLeftJoins = true): string {
        return $this->getSimplifiedQuery('1', $ignoreLeftJoins, true) . ' LIMIT 1';
    }

    /**
     * @param array $columns - formats:
     *  - array === []: all columns
     *  - array === ['*']: all columns
     *  - array format: [
     *      'col1Name',
     *      'TableAlias.col1Name',  //< it is possible but should not be used
     *      'alias1' => DbExpr::create('Count(*)'), //< converted to DbExpr::create('Count(*) as `alias1`'),
     *      'alias2' => 'col4',     //< converted to DbExpr::create('`col4` as `alias2`')
     *
     *  Specials for OrmSelect:
     *  - array === ['RelationName' => '*'] or ['RelationName' => ['*']] or ['RelationName.*']: all columns from RelationName
     *  - array === ['RelationName' => []]: no columns from RelationName but if join has type RIGHT JOIN or INNER JOIN - it will be joined
     *  - additional possibilities for array keys: [
     *      'RelationName.rel_col1',
     *      'RelationName' => [
     *          'rel_col1',
     *          'rel_col2',
     *          'SubRelationName' => [      //< this relation must be related to RelationName, not to main table
     *              'subrel_col1',
     *              'subrel_col2'
     *          ]
     *      ]
     *   ]
     * Note: all relations used here will be autoloaded
     * @return $this
     */
    public function columns(...$columns) {
        $this->columnsRaw = $columns;
        $this->columns = [];
        $this->setDirty('columns');
        return $this;
    }

    /**
     * Set distinct flag to query (SELECT DISTINCT fields ...) or ((SELECT DISTINCT ON ($columns) fields)
     * @param bool $value
     * @param null|array $columns - list of columns to be disctinct
     * @return $this
     */
    public function distinct(bool $value = true, ?array $columns = null) {
        $this->distinct = $value;
        $this->distinctColumns = [];
        if (!empty($columns)) {
            foreach ($columns as $columnName) {
                $this->distinctColumns[] = $this->analyzeColumnName($columnName, null, null, 'DISTINCT');
            }
        }
        $this->setDirty('distinct');
        return $this;
    }

    /**
     * Set Conditions
     * @param array $conditions - may contain:
     * - DbExpr instances
     * - key-value pairs where key is column name with optional operator (ex: 'col_name !='), value may be
     * any of DbExpr, int, float, string, array. Arrays used for operators like 'IN', 'BETWEEN', '=' and other that
     * accept or require multiple values. Some operator are smart-converted to the ones that fit the value. For
     * example '=' with array value will be converted to 'IN', '!=' with NULL value will be converted to 'IS NOT'
     * You can group conditions inside 'AND' and 'OR' keys. ex: ['col3 => 0, 'OR' => ['col1' => 1, 'col2' => 2]]
     * will be assembled into ("col3" = 0 AND ("col1" = 1 OR "col2" => 2)).
     * By default - 'AND' is used if you group conditions into array without a key:
     * ['col3 => 0, ['col1' => 1, 'col2' => 2]] will be assembled into ("col3" = 0 AND ("col1" = 1 AND "col2" => 2))
     * @param bool $append
     * @return $this
     * @throws \InvalidArgumentException
     * @see Utils::assembleWhereConditionsFromArray() for more details about operators and features
     */
    public function where(array $conditions, bool $append = false) {
        $this->where = $append ? array_merge($this->where, $conditions) : $conditions;
        $this->setDirty('where');
        $this->setDirty('joins');
        return $this;
    }

    /**
     * Add ORDER BY
     * @param string|DbExpr $columnName - 'field1', 'JoinName.field1', DbExpr::create('RAND()')
     * @param bool|string $direction - 'ASC' or true; 'DESC' or false; Optional suffixes: 'NULLS LAST' or 'NULLS FIRST';
     *      Ignored if $columnName instance of DbExpr
     * @param bool $append - true: append to existing orders | false: replace existsing orders
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function orderBy($columnName, $direction = 'asc', bool $append = true) {
        if (empty($columnName)) {
            throw new \InvalidArgumentException('$columnName argument cannot be empty');
        }
        $isDbExpr = $columnName instanceof DbExpr;
        if (!is_string($columnName) && !$isDbExpr) {
            throw new \InvalidArgumentException('$columnName argument must be a string or instance of DbExpr class');
        }
        if (is_bool($direction)) {
            $direction = $direction ? 'asc' : 'desc';
        } else if (!preg_match('%^(asc|desc)(\s*(nulls)\s*(first|last))?$%i', $direction)) {
            throw new \InvalidArgumentException(
                '$direction argument must be a boolean or string ("ASC" or "DESC" with optional "NULLS LAST" or "NULLS FIRST")'
            );
        }
        if (!$append) {
           $this->removeOrdering();
        }
        if ($isDbExpr) {
            $this->orderBy[] = $columnName;
        } else {
            $columnInfo = $this->analyzeColumnName($columnName, null, null, 'ORDER BY');
            $columnInfo['direction'] = $direction;
            $this->orderBy[$this->makeKeyForOrderBy($columnInfo)] = $columnInfo;
        }
        $this->setDirty('orderBy');
        return $this;
    }

    /**
     * @param array $columnInfo
     * @return string
     */
    protected function makeKeyForOrderBy(array $columnInfo): string {
        return ($columnInfo['join_name'] ?: $this->getTableAlias()) . '.' . $columnInfo['name'];
    }

    /**
     * @param string|DbExpr $columnName
     * @return bool
     */
    public function hasOrderingForColumn($columnName): bool {
        $columnInfo = $this->analyzeColumnName($columnName, null, null, 'ORDER BY');
        return isset($this->orderBy[$this->makeKeyForOrderBy($columnInfo)]);
    }

    /**
     * Remove order by (used to speedup existence test)
     * @return $this
     */
    public function removeOrdering() {
        $this->orderBy = [];
        $this->setDirty('orderBy');
        return $this;
    }

    /**
     * Add GROUP BY
     * @param array $columns - can contain 'col1' and 'ModelAlias.col1', DbExpr::create('expression')
     * @param bool $append - true: append to existing groups | false: replace existsing groups
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function groupBy(array $columns, bool $append = true) {
        if (!$append) {
            $this->groupBy = [];
        }
        foreach ($columns as $index => $columnName) {
            if ($columnName instanceof DbExpr) {
                $this->groupBy[] = $columnName;
            } else if (is_string($columnName)) {
                $columnInfo = $this->analyzeColumnName($columnName, null, null, 'GROUP BY');
                $key = ($columnInfo['join_name'] ?: $this->getTableAlias()) . '.' . $columnInfo['name'];
                $this->groupBy[$key] = $columnInfo;
            } else {
                throw new \InvalidArgumentException(
                    "\$columns argument contains invalid value at index '{$index}'. All values must be a strings or instances of DbExpr class"
                );
            }
        }
        $this->setDirty('groupBy');
        return $this;
    }

    /**
     * Set LIMIT
     * @param int $limit - 0 = no limit;
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function limit(int $limit) {
        if ($limit < 0) {
            throw new \InvalidArgumentException('$limit argument must be an integer value >= 0');
        }
        $this->limit = $limit;
        return $this;
    }

    /**
     * Remove LIMIT
     * @return $this
     */
    public function noLimit() {
        $this->limit(0);
        return $this;
    }

    public function getLimit(): int {
        return $this->limit;
    }

    /**
     * Set/Remove OFFSET
     * @param int $offset - 0 = no offset
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function offset(int $offset) {
        if ($offset < 0) {
            throw new \InvalidArgumentException('$offset argument must be an integer value >= 0');
        }
        $this->offset = $offset;
        return $this;
    }

    public function getOffset(): int {
        return $this->offset;
    }

    public function getOrderByColumns(): array {
        return $this->orderBy;
    }

    public function getGroupByColumns(): array {
        return $this->groupBy;
    }

    /**
     * Set LIMIT and OFFSET at once
     * @param int $limit - 0 = no limit;
     * @param int $offset - 0 = no offset
     * @return $this
     */
    public function page(int $limit, int $offset = 0) {
        return $this->limit($limit)->offset($offset);
    }

    /**
     * @param array $conditions
     * @return $this
     */
    public function having(array $conditions) {
        $this->having = $conditions;
        $this->setDirty('having');
        return $this;
    }

    /**
     * @param AbstractSelect $select - a sub select that can be used as "table" in main select
     * @param string $selectAlias - alias for passed $select (access to the select will be available using this alias)
     * @param bool $append
     * @return $this
     */
    public function with(AbstractSelect $select, string $selectAlias, bool $append = true) {
        if (!$this->getConnection()->isValidDbEntityName($selectAlias, false)) {
            throw new \InvalidArgumentException(
                '$selectAlias argument does not fit DB entity naming rules (usually alphanumeric string with underscores)'
            );
        }
        if (!$append) {
            $this->with = [];
        }
        if (isset($this->with[$selectAlias])) {
            throw new \InvalidArgumentException("WITH query with name '{$selectAlias}' already defined");
        }
        $this->with[$selectAlias] = $select;
        foreach ($select->getWithQueries() as $subAlias => $subselect) {
            if (!isset($this->with[$subAlias])) {
                $this->with($subselect, $subAlias);
            }
        }
        $this->setDirty('with');
        return $this;
    }

    /**
     * @return AbstractSelect[]
     */
    protected function getWithQueries(): array {
        return $this->with;
    }

    /**
     * @param AbstractJoinInfo $joinConfig
     * @param bool $append - false: reset joins list so it will only contain this join
     * @return $this
     * @throws \InvalidArgumentException
     */
    protected function _join(AbstractJoinInfo $joinConfig, bool $append = true) {
        if (!$joinConfig->isValid()) {
            throw new \InvalidArgumentException("Join config with name '{$joinConfig->getJoinName()}' is not valid");
        }
        if (!$append) {
            $this->joins = [];
        }
        if (isset($this->joins[$joinConfig->getJoinName()])) {
            throw new \InvalidArgumentException("Join with name '{$joinConfig->getJoinName()}' already defined");
        }
        $this->joins[$joinConfig->getJoinName()] = $joinConfig;
        $this->setDirty('joins');
        return $this;
    }

    /* ------------------------------------> SERVICE METHODS <-----------------------------------> */

    /**
     * @param string $subject - set subject as dirty
     * @return $this
     */
    protected function setDirty(string $subject) {
        if ($this->isDirty !== null) {
            $this->isDirty[] = $subject;
        }
        return $this;
    }

    /**
     * @param null|string $subject - null: any dirt? | string: is $subject dirty?
     * @return bool
     */
    protected function isDirty(?string $subject = null): bool {
        if ($subject === null) {
            return $this->isDirty === null || !empty($this->isDirty);
        } else {
            return $this->isDirty === null || in_array($subject, $this->isDirty, true);
        }
    }

    protected function notDirty() {
        $this->isDirty = [];
        return $this;
    }

    protected function beforeQueryBuilding() {
        $this->shortJoinAliases = [];
        if ($this->isDirty('where') || $this->isDirty('having')) {
            $this->joinsUsedInWhereAndHavingConditions = [];
        }
        if ($this->isDirty('columns')) {
            $this->processRawColumns();
        }
    }

    /**
     * @return $this
     */
    protected function processRawColumns() {
        $this->columnAliasToColumnInfo = [];
        $this->columns = $this->normalizeColumnsList($this->columnsRaw, null, true, 'SELECT');
        return $this;
    }

    /**
     * Analyze $columnName and return information about column
     * Examples:
     *  1.1 'column1' => [
     *    'name' => 'column1',
     *    'alias' => null,
     *    'join_name' => null,
     *    'type_cast' => null,
     *  ]
     *  1.2 '*' => [
     *    'name' => '*',
     *    'alias' => null,
     *    'join_name' => null,
     *    'type_cast' => null,
     *  ]
     *  2.1. 'TableAlias.column2 as alias1' => [
     *    'name' => 'column2',
     *    'alias' => 'alias1',
     *    'join_name' => null, //< $this->getTableAlias() === 'TableAlias' - no join here
     *    'type_cast' => null,
     *  ]
     *  2.2. 'TableAlias.* as alias1' => [
     *    'name' => 'column2',
     *    'alias' => null,
     *    'join_name' => null, //< $this->getTableAlias() === 'TableAlias' - no join here
     *    'type_cast' => null,
     *  ]
     *  3.1. 'JoinName.column3' => [
     *    'name' => 'column3',
     *    'alias' => null,
     *    'join_name' => 'JoinName',
     *    'type_cast' => null,
     *  ]
     *  3.2. 'JoinName.column3' => [
     *    'name' => 'column3',
     *    'alias' => null,
     *    'join_name' => 'JoinName',
     *    'type_cast' => null,
     *  ]
     *  4. 'JoinName.column4::varchar' => [
     *    'name' => 'column4',
     *    'alias' => null,
     *    'join_name' => 'JoinName',
     *    'type_cast' => 'varchar',
     *  ]
     * @param DbExpr|string $columnName
     * @param string|null $columnAlias
     * @param string|null $joinName
     * @param string $errorsPrefix - prefix for error messages
     * @return array - contains keys: 'name', 'alias', 'join_name', 'type_cast'. All keys are strings or nulls (except 'name')
     * @throws \InvalidArgumentException
     */
    protected function analyzeColumnName(
        $columnName,
        ?string $columnAlias = null,
        ?string $joinName = null,
        string $errorsPrefix = ''
    ): array {
        $errorsPrefix = trim($errorsPrefix) === '' ? '' : $errorsPrefix . ': ';
        $isDbExpr = $columnName instanceof DbExpr;
        if (!is_string($columnName) && !$isDbExpr) {
            throw new \InvalidArgumentException($errorsPrefix . '$columnName argument must be a string or instance of DbExpr class');
        } else if (!$isDbExpr && trim($columnName) === '') {
            throw new \InvalidArgumentException($errorsPrefix . '$columnName argument is not allowed to be an empty string');
        }
        /** @var DbExpr|string $columnName */
        $cacheKey = ($isDbExpr ? $columnName->get() : $columnName) . "_{$columnAlias}_{$joinName}";
        if (isset($this->analyzedColumns[$cacheKey])) {
            return $this->analyzedColumns[$cacheKey];
        }
        if ($columnAlias !== null) {
            $columnAlias = trim($columnAlias);
            if ($columnAlias === '') {
                throw new \InvalidArgumentException($errorsPrefix . '$columnAlias argument is not allowed to be an empty string');
            }
        }
        if ($joinName !== null) {
            $joinName = trim($joinName);
            if ($joinName === '') {
                throw new \InvalidArgumentException($errorsPrefix . '$joinName argument is not allowed to be an empty string');
            }
        }
        if ($isDbExpr) {
            $ret = [
                'name' => $columnName,
                'alias' => $columnAlias,
                'join_name' => $joinName,
                'type_cast' => null
            ];
            unset($columnName, $joinName, $columnAlias); //< to prevent faulty usage
        } else {
            $columnName = trim($columnName);
            $ret = Utils::splitColumnName($columnName);
            if ($ret['join_name'] && $columnAlias && $ret['join_name'] !== $joinName) {
                $ret['parent'] = $joinName ?: $this->getTableAlias();
            }
            if ($columnAlias) {
                // overwrite column alias when provided
                $ret['alias'] = $columnAlias;
            }
            
            if ($joinName && !$ret['join_name']) {
                $ret['join_name'] = $joinName;
            }
            unset($columnName, $joinName, $columnAlias); //< to prevent faulty usage
    
            if ($ret['name'] === '*') {
                $ret['type_cast'] = null;
                $ret['alias'] = null;
                $ret['json_selector'] = null;
            } else if (!$this->getConnection()->isValidDbEntityName($ret['json_selector'] ?: $ret['name'], true)) {
                if ($ret['json_selector']) {
                    throw new \InvalidArgumentException("{$errorsPrefix}Invalid json selector: [{$ret['json_selector']}]");
                } else {
                    throw new \InvalidArgumentException("{$errorsPrefix}Invalid column name: [{$ret['name']}]");
                }
            }
            
        }
        
        // nullify join name if it same as current table alias
        if (
            $ret['join_name'] === $this->getTableAlias()
            || (
                in_array($ret['join_name'], $this->shortJoinAliases, true)
                && array_flip($this->shortJoinAliases)[$ret['join_name']] === $this->getTableAlias()
            )
        ) {
            $ret['join_name'] = null;
        }
        
        return $ret;
    }

    /**
     * @param string $columnNameOrAlias
     * @param null|string $tableAliasOrJoinName
     * @return string
     */
    protected function makeColumnAlias(string $columnNameOrAlias, ?string $tableAliasOrJoinName = null): string {
        $joinShortAlias = $this->getShortJoinAlias($tableAliasOrJoinName ?: $this->getTableAlias());
        return '_' . $joinShortAlias . '__' . $this->getShortColumnAlias($columnNameOrAlias);
    }

    /**
     * @param array $columnInfo - return of $this->analyzeColumnName($columnName)
     * @param bool $itIsWithQuery - true: building a query for WITH
     * @return string - something like: "JoinAlias"."column_name"::typecast as "ColumnAlias"
     * @throws \InvalidArgumentException
     */
    protected function makeColumnNameWithAliasForQuery(array $columnInfo, bool $itIsWithQuery = false): string {
        $tableAlias = $columnInfo['join_name'] ?: $this->getTableAlias();
        $isDbExpr = $columnInfo['name'] instanceof DbExpr;
        if ($isDbExpr) {
            $columnName = $this->quoteDbExpr($columnInfo['name']);
        } else {
            $columnName = $this->quoteDbEntityName($this->getShortJoinAlias($tableAlias)) . '.' . $this->quoteDbEntityName($columnInfo['name']);
        }
        if ($columnInfo['type_cast']) {
            $columnName = $this->getConnection()->addDataTypeCastToExpression($columnInfo['type_cast'], $columnName);
        }
        if ($columnInfo['name'] === '*' || ($isDbExpr && empty($columnInfo['alias']))) {
            return $columnName;
        } else if ($itIsWithQuery) {
            if ($columnInfo['alias']) {
                return $columnName . ' AS ' . $this->quoteDbEntityName($columnInfo['alias']);
            } else {
                return $columnName;
            }
        } else {
            $columnAlias = $this->quoteDbEntityName($this->makeColumnAliasFromColumnInfo($columnInfo));
            return $columnName . ' AS ' . $columnAlias;
        }
    }
    
    protected function makeColumnAliasFromColumnInfo(array $columnInfo): ?string {
        if ($columnInfo['name'] instanceof DbExpr && !$columnInfo['alias']) {
            return null;
        }
        if (isset($columnInfo['parent'])) {
            $tableAlias = $columnInfo['parent'];
        } else {
            $tableAlias = $columnInfo['join_name'] ?: $this->getTableAlias();
        }
        return $this->makeColumnAlias($columnInfo['alias'] ?: $columnInfo['name'], $tableAlias);
    }

    /**
     * @param string $tableName
     * @param string $tableAlias
     * @param string|null $tableSchema
     * @return string - something like "table_name" AS "ShortAlias" or "schema_name"."table_name" AS "ShortAlias"
     */
    protected function makeTableNameWithAliasForQuery(string $tableName, string $tableAlias, ?string $tableSchema = null): string {
        $schema = !empty($tableSchema) && $this->getConnection()->isDbSupportsTableSchemas()
            ? $this->quoteDbEntityName($tableSchema) . '.'
            : '';
        return $schema . $this->quoteDbEntityName($tableName) . ' AS ' . $this->quoteDbEntityName($this->getShortJoinAlias($tableAlias));
    }

    /**
     * @param array $columnInfo - return of $this->analyzeColumnName($columnName)
     * @param string $subject - may be used for exception messages in child classes
     * @return string `TableAlias`.`column_name`::typecast
     */
    protected function makeColumnNameForCondition(array $columnInfo, string $subject = 'WHERE'): string {
        $tableAlias = $columnInfo['join_name'] ?: $this->getTableAlias();
        $columnName = $this->quoteDbEntityName($this->getShortJoinAlias($tableAlias)) . '.'
            . $this->quoteDbEntityName($columnInfo['json_selector'] ?: $columnInfo['name']);
        if ($columnInfo['type_cast']) {
            $columnName = $this->getConnection()->addDataTypeCastToExpression($columnInfo['type_cast'], $columnName);
        }
        return $columnName;
    }

    /**
     * Add columns into options and resolve contains
     * @param array $conditionsAndOptions
     * @return array
     */
    protected function normalizeConditionsAndOptionsArray(array $conditionsAndOptions): array {
        if (isset($conditionsAndOptions['JOIN'])) {
            $conditionsAndOptions['JOINS'] = $conditionsAndOptions['JOIN'];
            unset($conditionsAndOptions['JOIN']);
        }
        return $conditionsAndOptions;
    }

    protected function getShortJoinAlias(string $alias): string  {
        if (!isset($this->shortJoinAliases[$alias])) {
            // maybe it is already an alias?
            if (preg_match('%^[a-z][a-zA-Z0-9]{8}\d$%', $alias) && in_array($alias, $this->shortJoinAliases, true)) {
                return $alias;
                // todo: should it throw an exception instead?
            }
            $this->shortJoinAliases[$alias] = mb_strlen($alias) > 16
                ? chr(random_int(97, 122)) . hash('crc32b', $alias) . random_int(0, 9)
                : $alias;
        }
        return $this->shortJoinAliases[$alias];
    }

    protected function getShortColumnAlias(string $alias): string {
        if (!isset($this->shortColumnAliases[$alias])) {
            $this->shortColumnAliases[$alias] = mb_strlen($alias) > 16
                ? chr(random_int(97, 122)) . hash('crc32b', $alias) . random_int(0, 9)
                : $alias;
        }
        return $this->shortColumnAliases[$alias];
    }

    /**
     * Replace long join names and table alias by short ones inside $dbExpr
     * @param DbExpr $dbExpr
     * @return DbExpr
     */
    protected function modifyTableAliasAndJoinNamesInDbExpr(DbExpr $dbExpr): DbExpr {
        $tableAlias = $this->getTableAlias();
        $replaces = ["%`{$tableAlias}`\.%" => '`' . $this->getShortJoinAlias($tableAlias) . '`.'];
        foreach ($this->joins as $joinConfig) {
            $joinName = $joinConfig->getJoinName();
            $replaces["%`{$joinName}`\.%"] = '`' . $this->getShortJoinAlias($joinName) . '`.';
        }
        return $dbExpr->applyReplaces($replaces);
    }

    /**
     * @param array $columns
     * @param null|string $joinName
     * @param bool $allowSubJoins - true: allow colums like ['Join1' => ['Join2.*']]
     * @param string $subject - prefix used for error messages
     * @return array
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    protected function normalizeColumnsList(
        array $columns,
        ?string $joinName = null,
        bool $allowSubJoins = false,
        string $subject = 'SELECT'
    ): array {
        if (count($columns) === 1 && isset($columns[0]) && is_array($columns[0])) {
            /** @var array $columns */
            $columns = $columns[0];
        }
        if (empty($columns)) {
            if ($joinName === null) {
                $columns = ['*'];
            } else {
                return [];
            }
        }
        $normalizedColumns = [];
        foreach ($columns as $columnAlias => $columnName) {
            if ($columnAlias === '*') {
                if (!is_string($columnName) && !is_array($columnName)) {
                    throw new \InvalidArgumentException(
                        "Invalid excluded columns list for a key '$columnAlias'. "
                        . 'Value must be a string or array.'
                    );
                }
                // we're goo to go - no more validation needed
            } else if (
                !is_numeric($columnAlias)
                && (
                    is_array($columnName)
                    || $columnName === '*'
                )
            ) {
                $this->resolveColumnsToBeSelectedForJoin($columnAlias, $columnName, $joinName, false);
                continue;
            } else if (!is_string($columnName) && !($columnName instanceof DbExpr)) {
                throw new \InvalidArgumentException(
                    "Invalid column name for a key '$columnAlias'. "
                    . '$columns argument must contain only strings and instances of DbExpr class.'
                );
            } else if (empty($columnName)) {
                throw new \InvalidArgumentException(
                    "\$columns argument contains an empty column name for a key '$columnAlias'"
                );
            }
            if (empty($columnAlias) && $columnAlias !== 0) {
                throw new \InvalidArgumentException(
                    '$columns argument contains an empty column alias'
                );
            }
            $columnAlias = is_int($columnAlias) ? null : $columnAlias;
            if ($columnName === '*') {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $normalizedColumns = array_merge(
                    $normalizedColumns,
                    $this->normalizeWildcardColumn($joinName, [])
                );
            } else if ($columnAlias === '*') {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $normalizedColumns = array_merge(
                    $normalizedColumns,
                    $this->normalizeWildcardColumn($joinName, (array)$columnName)
                );
            } else {
                $columnInfo = $this->analyzeColumnName($columnName, $columnAlias, $joinName, $subject);
                if ($columnInfo['join_name'] !== $joinName && !isset($columnInfo['parent'])) {
                    // Note: ($joinName === null) restricts situation like
                    // new JoinInfo('Join2')->setForeignColumnsToSelect(['SomeOtehrJoin.col'])
                    if ($allowSubJoins) {
                        $this->resolveColumnsToBeSelectedForJoin(
                            $columnInfo['join_name'],
                            $columnInfo['alias'] ? [$columnInfo['alias'] => $columnInfo['name']] : [$columnInfo['name']],
                            null,
                            true
                        );
                    } else {
                        throw new \UnexpectedValueException(
                            "Invalid join name '{$columnInfo['join_name']}' used in columns list for join named '{$joinName}'"
                        );
                    }
                } else {
                    $normalizedColumns[] = $columnInfo;
                }
            }
        }
        return $normalizedColumns;
    }

    /**
     * Normalize '*' column name
     * @param null|string $joinName
     * @param null|array $excludeColumns - list of columns to exclude from wildcard (handled only by OrmSelect)
     * @return array - returns list of $this->analyzeColumnName() results
     */
    protected function normalizeWildcardColumn(?string $joinName = null, ?array $excludeColumns = null): array {
        return [$this->analyzeColumnName('*', null, $joinName, 'SELECT')];
    }

    /**
     * Decide what to do if join name mentioned in columns list
     * @param string $joinName
     * @param array|string $columns - string === '*' only
     * @param string $parentJoinName
     * @param bool $appendColumnsToExisting - true: $columns will be appended | false: $columns will replace existing ones
     * @throws \UnexpectedValueException
     */
    protected function resolveColumnsToBeSelectedForJoin(
        string $joinName,
        $columns,
        ?string $parentJoinName = null,
        bool $appendColumnsToExisting = false
    ) {
        throw new \UnexpectedValueException(
            "You must use JoinInfo->setForeignColumnsToSelect() to set the columns list to select for join named '{$joinName}'"
        );
    }

    /**
     * @param array $conditions
     * @param string $subject - can be 'WHERE', 'HAVING' or ''
     * @param null|string $joinName - string: used when assembling conditions for join
     * @return string
     */
    protected function makeConditions(array $conditions, string $subject = 'WHERE', ?string $joinName = null): string {
        $assembled = Utils::assembleWhereConditionsFromArray(
            $this->getConnection(),
            $conditions,
            function ($columnName) use ($joinName, $subject) {
                return $this->columnQuoterForConditions($columnName, $joinName, $subject);
            },
            'AND',
            function ($columnName, $rawValue) {
                if ($rawValue instanceof DbExpr) {
                    return $this->quoteDbExpr($rawValue);
                } else if ($rawValue instanceof AbstractSelect) {
                    return '(' . $rawValue->getQuery() . ')';
                } else {
                    return $rawValue;
                }
            }
        );
        $assembled = trim($assembled);
        return empty($assembled) ? '' : " {$subject} {$assembled}";
    }

    /**
     * @param string|DbExpr $columnName
     * @param string|null $joinName
     * @param string $subject - 'WHERE', 'HAVING', etc - the part of a query we are qouting the column for
     * @return string
     */
    protected function columnQuoterForConditions($columnName, ?string $joinName, string $subject): string {
        $columnInfo = $this->analyzeColumnName($columnName, null, $joinName, $subject);
        if (!empty($columnInfo['join_name']) && in_array($subject, ['WHERE', 'HAVING'], true)) {
            $this->joinsUsedInWhereAndHavingConditions[] = $columnInfo['join_name'];
        }
        return $this->makeColumnNameForCondition($columnInfo, $subject);
    }

    protected function makeGroupBy(): string  {
        $groups = [];
        foreach ($this->groupBy as $column) {
            if ($column instanceof DbExpr) {
                $groups[] = $this->quoteDbExpr($column);
            } else {
                $groups[] = $this->makeColumnNameForCondition($column, 'GROUP BY');
            }
        }
        return count($groups) ? ' GROUP BY ' . implode(', ', $groups) : '';
    }

    protected function makeOrderBy(): string {
        $orders = [];
        foreach ($this->orderBy as $columnInfo) {
            if ($columnInfo instanceof DbExpr) {
                $orders[] = $this->quoteDbExpr($columnInfo->setWrapInBrackets(false));
            } else {
                $orders[] = $this->makeColumnNameForCondition($columnInfo, 'ORDER BY') . ' ' . $columnInfo['direction'];
            }
        }
        return count($orders) ? ' ORDER BY ' . implode(', ', $orders) : '';
    }

    protected function makeJoins(bool $ignoreLeftJoins = false): string {
        $joins = [];
        foreach ($this->joins as $joinConfig) {
            if (
                $ignoreLeftJoins
                && $joinConfig->getJoinType() === $joinConfig::JOIN_LEFT
                && !$this->isJoinUsedInWhereOrHavingConditions($joinConfig)
            ) {
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
        return count($joins) ? ' ' . implode(' ', $joins) : '';
    }

    protected function isJoinUsedInWhereOrHavingConditions(AbstractJoinInfo $joinConfig): bool {
        return (
            in_array($this->getShortJoinAlias($joinConfig->getJoinName()), $this->joinsUsedInWhereAndHavingConditions, true)
            || in_array($joinConfig->getJoinName(), $this->joinsUsedInWhereAndHavingConditions, true)
        );
    }

    protected function makeJoinConditions(AbstractJoinInfo $joinConfig): string {
        $shortJoinName = $this->getShortJoinAlias($joinConfig->getJoinName());
        $conditions = array_merge(
            [
                $this->getShortJoinAlias($joinConfig->getTableAlias()) . '.' . $joinConfig->getColumnName()
                    => DbExpr::create("`{$shortJoinName}`.`{$joinConfig->getForeignColumnName()}`", false),
            ],
            $joinConfig->getAdditionalJoinConditions()
        );
        return trim($this->makeConditions($conditions, '', $joinConfig->getJoinName()));
    }

    protected function makeWithQueries(): string {
        $queries = [];
        foreach ($this->with as $alias => $select) {
            $select->replaceWithQueries(array_diff_key($this->with, [$alias => '']));
            $queries[] = $this->quoteDbEntityName($this->getShortJoinAlias($alias)) . ' AS (' . $select->buildQueryToBeUsedInWith() . ')';
        }
        return count($queries) ? 'WITH ' . implode(', ', $queries) . ' ' : '';
    }

    /**
     * Replace all WITH queries for this select. Used by main select to populate WITH queries among all WITH queries
     * so that there will be no problems building queries that depend on other WITH queries.
     * Make sure to remove current query from $withQueries so there will be no problems
     * @param array $withQueries
     * @return $this
     */
    protected function replaceWithQueries(array $withQueries) {
        $this->with = $withQueries;
        $this->setDirty('with');
        return $this;
    }

    /**
     * @param bool $itIsWithQuery - true: building a query for WITH
     * @return string
     * @throws \UnexpectedValueException
     */
    protected function makeColumnsForQuery(bool $itIsWithQuery = false): string {
        $columns = [];
        foreach ($this->columns as $columnInfo) {
            if (is_string($columnInfo)) {
                $columns[] = $columnInfo;
            } else {
                $columnAlias = $this->makeColumnAliasFromColumnInfo($columnInfo);
                if ($columnAlias) {
                    $this->columnAliasToColumnInfo[$columnAlias] = $columnInfo;
                }
                $columns[] = $this->makeColumnNameWithAliasForQuery($columnInfo, $itIsWithQuery);
            }
        }
        $columns = array_merge($columns, $this->collectJoinedColumnsForQuery($itIsWithQuery));
        if (empty($columns)) {
            throw new \UnexpectedValueException('There are no columns to select');
        }
        return $this->makeDistinctForQuery() . implode(', ', $columns);
    }
    
    protected function makeDistinctForQuery() {
        if (!$this->distinct) {
            return '';
        }
        $ret = 'DISTINCT ';
        if (!empty($this->distinctColumns)) {
            $columns = [];
            foreach ($this->distinctColumns as $columnInfo) {
                $columns[] = $this->makeColumnNameForCondition($columnInfo, 'DISTINCT');
            }
            $ret .= ' ON (' . implode(',', $columns) . ') ';
        }
        return $ret;
    }

    /**
     * @param bool $itIsWithQuery - true: building a query for WITH
     * @return array
     */
    protected function collectJoinedColumnsForQuery(bool $itIsWithQuery = false): array {
        $columns = [];
        foreach ($this->joins as $joinConfig) {
            $columnsToJoin = $joinConfig->getForeignColumnsToSelect();
            if (empty($columnsToJoin)) {
                continue;
            }
            $joinColumns = $this->normalizeColumnsList(
                $columnsToJoin,
                $joinConfig->getJoinName(),
                false,
                'JOIN [' . $joinConfig->getJoinName() . ']'
            );
            foreach ($joinColumns as $columnInfo) {
                if (is_string($columnInfo)) {
                    $columns[] = $columnInfo;
                } else {
                    $columnAlias = $this->makeColumnAliasFromColumnInfo($columnInfo);
                    if ($columnAlias) {
                        $this->columnAliasToColumnInfo[$columnAlias] = $columnInfo;
                    }
                    $columns[] = $this->makeColumnNameWithAliasForQuery($columnInfo, $itIsWithQuery);
                }
            }
        }
        return $columns;
    }

    protected function makeLimit(): string {
        return $this->limit > 0 ? ' LIMIT ' . $this->limit : '';
    }

    protected function makeOffset(): string {
        return $this->offset > 0 ? ' OFFSET ' . $this->offset : '';
    }

    /**
     * Convert key-value array received from DB to nested array with joins data stored under join names inside
     * main array. Also decodes columns aliases (keys in original array)
     * @param array $record
     * @param array $shortColumnAliasToAlias = $shortColumnAliasToAlias = array_flip($this->shortColumnAliases);
     * @param array $shortJoinAliasToAlias = $shortJoinAliasToAlias = array_flip($this->shortJoinAliases);
     * @return array - ['col1' => 'val1', 'col2' => 'val2', 'Join1Name' => ['jcol1' => 'jvalue1', ...], ...]
     */
    private function normalizeRecord(array $record, array $shortColumnAliasToAlias, array $shortJoinAliasToAlias): array {
        if (empty($record)) {
            return [];
        }
        $dataBlocks = [$this->getTableAlias() => []];
        // process record's column aliases and group column values by table alias
        foreach ($record as $columnAlias => $value) {
            if (isset($this->columnAliasToColumnInfo[$columnAlias])) {
                $colInfo = $this->columnAliasToColumnInfo[$columnAlias];
                $group = isset($colInfo['parent']) ? $colInfo['parent'] : $colInfo['join_name'];
                $dataBlocks[$group ?: $this->getTableAlias()][$colInfo['alias'] ?: $colInfo['name']] = $value;
            } else if (preg_match('%^_(.+?)__(.+?)$%', $columnAlias, $colInfo)) {
                [, $tableAlias, $column] = $colInfo;
                if (isset($shortJoinAliasToAlias[$tableAlias])) {
                    $tableAlias = $shortJoinAliasToAlias[$tableAlias];
                }
                if (isset($shortColumnAliasToAlias[$column])) {
                    $column = $shortColumnAliasToAlias[$column];
                }
                if (empty($dataBlocks[$tableAlias])) {
                    $dataBlocks[$tableAlias] = [];
                }
                $dataBlocks[$tableAlias][$column] = $value;
            } else {
                $dataBlocks[$this->getTableAlias()][$columnAlias] = $value;
            }
        }
        // make record nested + add missing child records
        $nested = $dataBlocks[$this->getTableAlias()] ?: [];
        $deepNestedJoins = [];
        foreach ($this->joins as $joinConfig) {
            if (!empty($dataBlocks[$joinConfig->getJoinName()])) {
                $data = $this->normalizeJoinDataForRecord($joinConfig, $dataBlocks[$joinConfig->getJoinName()]);
                if ($joinConfig->getTableAlias() === $this->getTableAlias()) {
                    $nested[$joinConfig->getJoinName()] = $data;
                } else {
                    $deepNestedJoins[] = [
                        'config' => $joinConfig,
                        'data' => $data,
                    ];
                }
            } else if (count($joinConfig->getForeignColumnsToSelect()) > 0) {
                if ($joinConfig->getTableAlias() === $this->getTableAlias()) {
                    $nested[$joinConfig->getJoinName()] = [];
                } else {
                    $deepNestedJoins[] = [
                        'config' => $joinConfig,
                        'data' => [],
                    ];
                }
            }
        }
        if (count($deepNestedJoins) > 0) {
            $this->placeDataOfDeepNestedJoinsIntoRecord($deepNestedJoins, $nested);
        }
        return $nested;
    }

    /**
     * Insert deeply nested joins data into record data
     * @param array $joins
     * @param array $data
     */
    protected function placeDataOfDeepNestedJoinsIntoRecord(array $joins, array &$data) {
        /** @var JoinInfo[] $usedJoins */
        $usedJoins = [];
        foreach ($joins as $index => $join) {
            /** @var JoinInfo $config */
            $config = $join['config'];
            if (array_key_exists($config->getTableAlias(), $data)) {
                $data[$config->getTableAlias()][$config->getJoinName()] = $join['data'];
                $usedJoins[] = $config;
                unset($joins[$index]);
            }
        }
        if (count($usedJoins) > 0) {
            foreach ($usedJoins as $config) {
                if (count($joins) > 0) {
                    $this->placeDataOfDeepNestedJoinsIntoRecord($joins, $data[$config->getTableAlias()]);
                }
                if (empty($data[$config->getTableAlias()][$config->getJoinName()])) {
                    unset($data[$config->getTableAlias()][$config->getJoinName()]);
                }
                if (empty($data[$config->getTableAlias()])) {
                    unset($data[$config->getTableAlias()]);
                }
            }
        }
    }

    /**
     * Normalize data received for related record or join
     * @param AbstractJoinInfo $joinConfig
     * @param array $data
     * @return array
     */
    protected function normalizeJoinDataForRecord(AbstractJoinInfo $joinConfig, array $data): array {
        return $data;
    }

    /**
     * @param DbExpr $expr
     * @return string
     */
    protected function quoteDbExpr(DbExpr $expr): string {
        return $this->getConnection()->quoteDbExpr($this->modifyTableAliasAndJoinNamesInDbExpr($expr));
    }

    /**
     * @param string $name
     * @return string
     */
    protected function quoteDbEntityName($name): string {
        return $this->getConnection()->quoteDbEntityName($name);
    }

    /**
     * @param mixed $value
     * @return string
     */
    protected function quoteValue($value): string {
        return $this->getConnection()->quoteValue($value);
    }

    /**
     * Validate if there are all joins defined for all table aliases used in query
     * @throws \UnexpectedValueException
     */
    protected function validateIfThereAreEnoughJoins() {
        $missingJoins = [];
        foreach ($this->shortJoinAliases as $fullAlias => $notUsed) {
            if (
                $fullAlias !== $this->getTableAlias()
                && !$this->hasJoin($fullAlias, false)
                && !$this->hasWithQuery($fullAlias, false)
            ) {
                $missingJoins[] = $fullAlias;
            }
        }
        if (count($missingJoins) > 0) {
            throw new \UnexpectedValueException(
                'There are no joins with names: ' . implode(', ', $missingJoins)
            );
        }
    }

//    abstract public function join(AbstractJoinInfo $joinInfo, bool $append = true); //< it is impossible to overwrite method to use child class instead of AbstractJoinInfo

    /**
     * @param string $alias
     * @param bool $mayBeShort - true: alias may be received from $this->getShortJoinAlias()
     * @return bool
     */
    protected function hasWithQuery(string $alias, bool $mayBeShort = false): bool {
        return isset($this->with[$alias]) || ($mayBeShort && in_array($alias, $this->shortJoinAliases, true));
    }

    /**
     * @param string $joinName
     * @param bool $mayBeShort - true: alias may be received from $this->getShortJoinAlias()
     * @return bool
     */
    protected function hasJoin(string $joinName, bool $mayBeShort = false): bool {
        return isset($this->joins[$joinName]) || ($mayBeShort && in_array($joinName, $this->shortJoinAliases, true));
    }

    /**
     * @param string $joinName
     * @return JoinInfo|OrmJoinInfo
     * @throws \UnexpectedValueException
     */
    protected function getJoin(string $joinName) {
        if (!$this->hasJoin($joinName, true)) {
            throw new \UnexpectedValueException("Join config with name [{$joinName}] not found");
        }
        if (isset($this->joins[$joinName])) {
            return $this->joins[$joinName];
        } else {
            return $this->joins[array_flip($this->shortJoinAliases)[$joinName]];
        }
    }

    public function __clone() {
        foreach ($this->joins as $key => $joinConfig) {
            $this->joins[$key] = clone $joinConfig;
        }
    }
}
