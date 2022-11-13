<?php

declare(strict_types=1);

namespace PeskyORM\Core;

use PeskyORM\Core\Utils\ArgumentValidators;
use PeskyORM\Core\Utils\PdoUtils;
use PeskyORM\Core\Utils\QueryBuilderUtils;

abstract class SelectQueryBuilderAbstract implements SelectQueryBuilderInterface
{
    protected array $shortJoinAliases = [];
    protected array $shortColumnAliases = [];
    protected array $columns = [];
    protected array $columnsRaw = ['*'];
    protected bool $distinct = false;
    protected array $distinctColumns = [];
    protected array $where = [];
    protected array $orderBy = [];
    protected array $groupBy = [];
    protected array $having = [];
    /**
     * @var SelectQueryBuilderInterface[]
     */
    protected array $with = [];
    protected int $limit = 0;
    protected int $offset = 0;
    /**
     * @var NormalJoinConfigInterface[]
     */
    protected array $joins = [];
    /**
     * @var CrossJoinConfigInterface[]
     */
    protected array $crossJoins = [];
    /**
     * List of JOINs names that are mentioned in WHERE and HAVING conditions.
     * This is used in simplified query builder, so it won't drop LEFT JOINs if
     * they are required for query to be successful
     */
    protected array $joinsUsedInWhereAndHavingConditions = [];
    protected array $analyzedColumns = [];
    /**
     * Indicates that Select has changed since last getQuery or getSimplifiedQuery call
     * @var null|array - null: all dirty | array - only some items are dirty
     */
    protected ?array $isDirty = null;
    protected array $columnAliasToColumnInfo = [];

    abstract public function getTableName(): string;

    abstract public function getTableAlias(): string;

    abstract public function getTableSchemaName(): ?string;

    abstract public function getConnection(): DbAdapterInterface;

    public function fromConfigsArray(array $conditionsAndOptions): static
    {
        $conditionsAndOptions = $this->normalizeConditionsAndOptionsArray($conditionsAndOptions);
        $this->parseNormalizedConfigsArray($conditionsAndOptions);
        return $this;
    }

    /**
     * @throws \InvalidArgumentException
     */
    protected function parseNormalizedConfigsArray(array $conditionsAndOptions): void
    {
        // WITH
        if (!empty($conditionsAndOptions['WITH'])) {
            ArgumentValidators::assertArrayKeyValueIsArray(
                "\$conditionsAndOptions['WITH']",
                $conditionsAndOptions['WITH']
            );
            foreach ($conditionsAndOptions['WITH'] as $selectAlias => $select) {
                $this->with($select, $selectAlias);
            }
        }
        // JOINS - must be 1st to allow columns validation in OrmSelect or other child class
        if (!empty($conditionsAndOptions['JOINS'])) {
            ArgumentValidators::assertArrayKeyValueIsArray(
                "\$conditionsAndOptions['JOINS']",
                $conditionsAndOptions['JOINS']
            );
            foreach ($conditionsAndOptions['JOINS'] as $key => $join) {
                if ($join instanceof CrossJoinConfigInterface) {
                    $this->crossJoin($join);
                } else if ($join instanceof NormalJoinConfigInterface) {
                    $this->join($join);
                } else {
                    throw new \InvalidArgumentException(
                        "\$conditionsAndOptions['JOINS'][$key]: value must be instance of "
                        . NormalJoinConfigInterface::class . ' or ' . CrossJoinConfigInterface::class . " class"
                    );
                }

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
            ArgumentValidators::assertArrayKeyValueIsArray(
                "\$conditionsAndOptions['ORDER']",
                $conditionsAndOptions['ORDER']
            );
            foreach ($conditionsAndOptions['ORDER'] as $columnName => $direction) {
                if ($direction instanceof DbExpr || is_int($columnName)) {
                    // DbExpr or column name without direction (use default direction)
                    $this->orderBy($direction);
                } else {
                    $this->orderBy($columnName, $direction);
                }
            }
        }
        // LIMIT
        if (!empty($conditionsAndOptions['LIMIT'])) {
            $this->limit($conditionsAndOptions['LIMIT']);
        }
        // OFFSET
        if (!empty($conditionsAndOptions['OFFSET'])) {
            $this->offset($conditionsAndOptions['OFFSET']);
        }
        // GROUP BY
        if (!empty($conditionsAndOptions['GROUP'])) {
            ArgumentValidators::assertArrayKeyValueIsArray(
                "\$conditionsAndOptions['GROUP']",
                $conditionsAndOptions['GROUP']
            );
            $this->groupBy($conditionsAndOptions['GROUP']);
        }
        // HAVING
        if (!empty($conditionsAndOptions['HAVING'])) {
            ArgumentValidators::assertArrayKeyValueIsArray(
                "\$conditionsAndOptions['HAVING']",
                $conditionsAndOptions['HAVING']
            );
            $this->having($conditionsAndOptions['HAVING']);
        }
        // CONDITIONS
        $conditions = array_diff_key($conditionsAndOptions, array_flip($this->getListOfSpecialKeysInConditionsAndOptions()));
        if (!empty($conditions)) {
            $this->where($conditions);
        }
    }

    protected function getListOfSpecialKeysInConditionsAndOptions(): array
    {
        return ['LIMIT', 'OFFSET', 'HAVING', 'GROUP', 'ORDER', 'JOINS', 'DISTINCT'];
    }

    public function fetchOne(): array
    {
        return $this->_fetch(PdoUtils::FETCH_FIRST);
    }

    public function fetchMany(): array
    {
        return $this->_fetch(PdoUtils::FETCH_ALL);
    }

    /**
     * @throws \BadMethodCallException
     */
    public function fetchNextPage(): array
    {
        if (!$this->limit) {
            throw new \BadMethodCallException('It is impossible to use pagination when there is no limit');
        }
        $this->offset($this->offset + $this->limit);
        return $this->_fetch(PdoUtils::FETCH_ALL);
    }

    /**
     * @throws \BadMethodCallException
     */
    public function fetchPrevPage(): array
    {
        if (!$this->limit) {
            throw new \BadMethodCallException('It is impossible to use pagination when there is no limit');
        }
        $this->offset($this->offset - $this->limit);
        return $this->_fetch(PdoUtils::FETCH_ALL);
    }

    public function fetchCount(bool $ignoreLeftJoins = true): int
    {
        return (int)$this->getConnection()
            ->query($this->getCountQuery($ignoreLeftJoins), PdoUtils::FETCH_VALUE);
    }

    public function fetchExistence(bool $ignoreLeftJoins = true): bool
    {
        return (int)$this->getConnection()
                ->query($this->getExistenceQuery($ignoreLeftJoins), PdoUtils::FETCH_VALUE) === 1;
    }

    public function fetchColumn(): array
    {
        return $this->_fetch(PdoUtils::FETCH_COLUMN);
    }

    public function fetchAssoc(DbExpr|string $keysColumn, DbExpr|string $valuesColumn): array
    {
        return $this->columns(['key' => $keysColumn, 'value' => $valuesColumn])
            ->_fetch(PdoUtils::FETCH_KEY_PAIR);
    }

    public function fetchValue(DbExpr $expression): mixed
    {
        return $this->columns([$expression])
            ->_fetch(PdoUtils::FETCH_VALUE);
    }

    /**
     * @param string $selectionType - one of PeskyORM\Core\Utils\PdoUtils::FETCH_*
     */
    protected function _fetch(string $selectionType): mixed
    {
        $data = $this->getConnection()->query($this->getQuery(), $selectionType);

        if (in_array($selectionType, [PdoUtils::FETCH_COLUMN, PdoUtils::FETCH_VALUE, PdoUtils::FETCH_KEY_PAIR], true)) {
            return $data;
        }

        if ($selectionType === PdoUtils::FETCH_FIRST) {
            $shortColumnAliasToAlias = array_flip($this->shortColumnAliases);
            $shortJoinAliasToAlias = array_flip($this->shortJoinAliases);
            return $this->normalizeRecord($data, $shortColumnAliasToAlias, $shortJoinAliasToAlias);
        }

        $records = [];
        $shortColumnAliasToAlias = array_flip($this->shortColumnAliases);
        $shortJoinAliasToAlias = array_flip($this->shortJoinAliases);
        foreach ($data as $record) {
            $records[] = $this->normalizeRecord($record, $shortColumnAliasToAlias, $shortJoinAliasToAlias);
        }
        return $records;
    }

    public function getQuery(): string
    {
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
    protected function getSimplifiedQuery(
        string $expression,
        bool $ignoreLeftJoins = true,
        bool $ignoreLimitAndOffset = false
    ): string {
        $this->beforeQueryBuilding();
        $with = $this->makeWithQueries();
        $fromTableAndOthers = $this->buildQueryPartsAfterSelectColumns($ignoreLeftJoins, false, !$ignoreLimitAndOffset);
        $this->validateIfThereAreEnoughJoins();
        $this->notDirty();
        return "{$with}SELECT $expression {$fromTableAndOthers}";
    }

    public function buildQueryToBeUsedInWith(): string
    {
        $this->beforeQueryBuilding();
        $columns = $this->makeColumnsForQuery(true);
        $fromTableAndOthers = $this->buildQueryPartsAfterSelectColumns(false, true, true);
        $this->validateIfThereAreEnoughJoins();
        $this->notDirty();
        return "SELECT {$columns} {$fromTableAndOthers}";
    }

    protected function buildQueryPartsAfterSelectColumns(
        bool $ignoreLeftJoins,
        bool $withOrderBy,
        bool $withLimitAndOffset
    ): string {
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

    public function getCountQuery(bool $ignoreLeftJoins = true): string
    {
        if ($this->distinct) {
            throw new \BadMethodCallException('Cannot determine column name to use for COUNT(DISTINCT {column_name})');
        }
        return $this->getSimplifiedQuery('COUNT(*)', $ignoreLeftJoins, true);
    }

    public function getExistenceQuery(bool $ignoreLeftJoins = true): string
    {
        return $this->getSimplifiedQuery('1', $ignoreLeftJoins, true) . ' LIMIT 1';
    }

    public function columns(...$columns): static
    {
        $this->columnsRaw = $columns;
        $this->columns = [];
        $this->setDirty('columns');
        return $this;
    }

    public function distinct(bool $value = true, ?array $columns = null): static
    {
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
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function where(array $conditions, bool $append = false): static
    {
        $this->where = $append ? array_merge($this->where, $conditions) : $conditions;
        $this->setDirty('where');
        $this->setDirty('joins');
        return $this;
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function orderBy(
        DbExpr|string $columnName,
        bool|string $direction = self::ORDER_DIRECTION_ASC,
        bool $append = true
    ): static {
        if (empty($columnName)) {
            throw new \InvalidArgumentException('$columnName argument cannot be empty');
        }
        $isDbExpr = $columnName instanceof DbExpr;
        if (is_bool($direction)) {
            $direction = $direction ? self::ORDER_DIRECTION_ASC : self::ORDER_DIRECTION_DESC;
        } elseif (!$isDbExpr && !preg_match('%^(asc|desc)(\s*(nulls)\s*(first|last))?$%i', $direction)) {
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

    protected function makeKeyForOrderBy(array $columnInfo): string
    {
        return ($columnInfo['join_name'] ?: $this->getTableAlias()) . '.' . $columnInfo['name'];
    }

    public function hasOrderingForColumn(DbExpr|string $columnName): bool
    {
        $columnInfo = $this->analyzeColumnName($columnName, null, null, 'ORDER BY');
        return isset($this->orderBy[$this->makeKeyForOrderBy($columnInfo)]);
    }

    public function removeOrdering(): static
    {
        $this->orderBy = [];
        $this->setDirty('orderBy');
        return $this;
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function groupBy(array $columns, bool $append = true): static
    {
        if (!$append) {
            $this->groupBy = [];
        }
        foreach ($columns as $index => $columnName) {
            if ($columnName instanceof DbExpr) {
                $this->groupBy[] = $columnName;
            } elseif (is_string($columnName)) {
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
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function limit(int $limit): static
    {
        ArgumentValidators::assertPositiveInteger('$limit', $limit, true);
        $this->limit = $limit;
        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function offset(int $offset): static
    {
        ArgumentValidators::assertPositiveInteger('$offset', $offset, true);
        $this->offset = $offset;
        return $this;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getOrderByColumns(): array
    {
        return $this->orderBy;
    }

    public function getGroupByColumns(): array
    {
        return $this->groupBy;
    }

    public function page(int $limit, int $offset = 0): static
    {
        return $this->limit($limit)
            ->offset($offset);
    }

    public function having(array $conditions): static
    {
        $this->having = $conditions;
        $this->setDirty('having');
        return $this;
    }

    public function with(SelectQueryBuilderAbstract $select, string $selectAlias, bool $append = true): static
    {
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
     * @return SelectQueryBuilderAbstract[]
     */
    protected function getWithQueries(): array
    {
        return $this->with;
    }

    /**
     * {@inheritDoc}
     * @throws \InvalidArgumentException
     */
    public function join(NormalJoinConfigInterface $joinConfig, bool $append = true): static
    {
        $this->validateJoin($joinConfig);
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

    protected function validateJoin(NormalJoinConfigInterface $joinConfig): void
    {
        if (!$joinConfig->isValid()) {
            throw new \InvalidArgumentException("Join config with name '{$joinConfig->getJoinName()}' is not valid");
        }
    }

    /**
     * @throws \InvalidArgumentException
     */
    public function crossJoin(CrossJoinConfig $joinConfig, bool $append = true): static
    {
        // @see CROSS JOIN docs for details
        if (!$joinConfig->isValid()) {
            throw new \InvalidArgumentException("Cross join config with name '{$joinConfig->getJoinName()}' is not valid");
        }
        if (!$append) {
            $this->crossJoins = [];
        }
        if (isset($this->crossJoins[$joinConfig->getJoinName()])) {
            throw new \InvalidArgumentException("Cross join with name '{$joinConfig->getJoinName()}' already defined");
        }
        $this->crossJoins[$joinConfig->getJoinName()] = $joinConfig;
        $this->setDirty('joins');
        return $this;
    }

    /* ------------------------------------> SERVICE METHODS <-----------------------------------> */

    /**
     * Set $subject as dirty
     */
    protected function setDirty(string $subject): static
    {
        if ($this->isDirty !== null) {
            $this->isDirty[] = $subject;
        }
        return $this;
    }

    /**
     * @param null|string $subject - null: any dirt? | string: is $subject dirty?
     */
    protected function isDirty(?string $subject = null): bool
    {
        if ($subject === null) {
            return $this->isDirty === null || !empty($this->isDirty);
        }

        return $this->isDirty === null || in_array($subject, $this->isDirty, true);
    }

    protected function notDirty(): static
    {
        $this->isDirty = [];
        return $this;
    }

    protected function beforeQueryBuilding(): void
    {
        $this->shortJoinAliases = [];
        if ($this->isDirty('where') || $this->isDirty('having')) {
            $this->joinsUsedInWhereAndHavingConditions = [];
        }
        if ($this->isDirty('columns')) {
            $this->processRawColumns();
        }
    }

    protected function processRawColumns(): static
    {
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
     * @param string|DbExpr $columnName
     * @param string|null $columnAlias
     * @param string|null $joinName
     * @param string $errorsPrefix - prefix for error messages
     * @return array - contains keys: 'name', 'alias', 'join_name', 'type_cast'. All keys are strings or nulls (except 'name')
     * @throws \InvalidArgumentException
     */
    protected function analyzeColumnName(
        DbExpr|string $columnName,
        ?string $columnAlias = null,
        ?string $joinName = null,
        string $errorsPrefix = ''
    ): array {
        $errorsPrefix = trim($errorsPrefix) === '' ? '' : $errorsPrefix . ': ';
        $isDbExpr = $columnName instanceof DbExpr;
        if (!$isDbExpr && trim($columnName) === '') {
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

            if (!$this->getConnection()->isValidDbEntityName($columnAlias)) {
                throw new \InvalidArgumentException($errorsPrefix . "\$columnAlias argument contains invalid db entity name: [$columnAlias]");
            }
        }
        if ($joinName !== null) {
            $joinName = trim($joinName);
            if ($joinName === '') {
                throw new \InvalidArgumentException($errorsPrefix . '$joinName argument is not allowed to be an empty string');
            }

            if (!$this->getConnection()->isValidDbEntityName($joinName)) {
                throw new \InvalidArgumentException($errorsPrefix . "\$joinName argument contains invalid db entity name: [$joinName]");
            }
        }
        if ($isDbExpr) {
            $ret = [
                'name' => $columnName,
                'alias' => $columnAlias,
                'join_name' => $joinName,
                'type_cast' => null,
            ];
            /** @noinspection UselessUnsetInspection */
            unset($columnName, $joinName, $columnAlias); //< to prevent faulty usage
        } else {
            $columnName = trim($columnName);
            $ret = QueryBuilderUtils::splitColumnName($columnName);
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
            /** @noinspection UselessUnsetInspection */
            unset($columnName, $joinName, $columnAlias); //< to prevent faulty usage

            if ($ret['name'] === '*') {
                $ret['type_cast'] = null;
                $ret['alias'] = null;
                $ret['json_selector'] = null;
            } elseif (!$this->getConnection()->isValidDbEntityName($ret['json_selector'] ?: $ret['name'], true)) {
                if ($ret['json_selector']) {
                    throw new \InvalidArgumentException("{$errorsPrefix}Invalid json selector: [{$ret['json_selector']}]");
                }

                throw new \InvalidArgumentException("{$errorsPrefix}Invalid column name: [{$ret['name']}]");
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

    protected function makeColumnAlias(string $columnNameOrAlias, ?string $tableAliasOrJoinName = null): string
    {
        $joinShortAlias = $this->getShortJoinAlias($tableAliasOrJoinName ?: $this->getTableAlias());
        return '_' . $joinShortAlias . '__' . $this->getShortColumnAlias($columnNameOrAlias);
    }

    /**
     * @param array $columnInfo - return of $this->analyzeColumnName($columnName)
     * @param bool $itIsWithQuery - true: building a query for WITH
     * @return string - something like: "JoinAlias"."column_name"::typecast as "ColumnAlias"
     * @throws \InvalidArgumentException
     */
    protected function makeColumnNameWithAliasForQuery(array $columnInfo, bool $itIsWithQuery = false): string
    {
        $tableAlias = $columnInfo['join_name'] ?: $this->getTableAlias();
        $isDbExpr = $columnInfo['name'] instanceof DbExpr;
        if ($isDbExpr) {
            $columnName = $this->quoteDbExpr($columnInfo['name']);
        } else {
            $columnName = $this->quoteDbEntityName($this->getShortJoinAlias($tableAlias)) . '.' . $this->quoteDbEntityName($columnInfo['name']);
        }
        if ($columnInfo['type_cast']) {
            $columnName = $this->getConnection()
                ->addDataTypeCastToExpression($columnInfo['type_cast'], $columnName);
        }
        if ($columnInfo['name'] === '*' || ($isDbExpr && empty($columnInfo['alias']))) {
            return $columnName;
        }

        if ($itIsWithQuery) {
            if ($columnInfo['alias']) {
                return $columnName . ' AS ' . $this->quoteDbEntityName($columnInfo['alias']);
            }

            return $columnName;
        }

        $columnAlias = $this->quoteDbEntityName($this->makeColumnAliasFromColumnInfo($columnInfo));
        return $columnName . ' AS ' . $columnAlias;
    }

    protected function makeColumnAliasFromColumnInfo(array $columnInfo): ?string
    {
        if ($columnInfo['name'] instanceof DbExpr && !$columnInfo['alias']) {
            return null;
        }
        $tableAlias = $columnInfo['parent'] ?? ($columnInfo['join_name'] ?: $this->getTableAlias());
        return $this->makeColumnAlias($columnInfo['alias'] ?: $columnInfo['name'], $tableAlias);
    }

    /**
     * @return string - something like "table_name" AS "ShortAlias" or "schema_name"."table_name" AS "ShortAlias"
     */
    protected function makeTableNameWithAliasForQuery(
        string $tableName,
        string $tableAlias,
        ?string $tableSchema = null
    ): string {
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
    protected function makeColumnNameForCondition(array $columnInfo, string $subject = 'WHERE'): string
    {
        $tableAlias = $columnInfo['join_name'] ?: $this->getTableAlias();
        $columnName = $this->quoteDbEntityName($this->getShortJoinAlias($tableAlias)) . '.'
            . $this->quoteDbEntityName($columnInfo['json_selector'] ?? $columnInfo['name']);
        if ($columnInfo['type_cast']) {
            $columnName = $this->getConnection()->addDataTypeCastToExpression($columnInfo['type_cast'], $columnName);
        }
        return $columnName;
    }

    /**
     * Add columns into options and resolve contains
     */
    protected function normalizeConditionsAndOptionsArray(array $conditionsAndOptions): array
    {
        if (isset($conditionsAndOptions['JOIN'])) {
            $conditionsAndOptions['JOINS'] = $conditionsAndOptions['JOIN'];
            unset($conditionsAndOptions['JOIN']);
        }
        return $conditionsAndOptions;
    }

    protected function getShortJoinAlias(string $alias): string
    {
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

    protected function getShortColumnAlias(string $alias): string
    {
        if (!isset($this->shortColumnAliases[$alias])) {
            $this->shortColumnAliases[$alias] = mb_strlen($alias) > 16
                ? chr(random_int(97, 122)) . hash('crc32b', $alias) . random_int(0, 9)
                : $alias;
        }
        return $this->shortColumnAliases[$alias];
    }

    /**
     * Replace long join names and table alias by short ones inside $dbExpr
     */
    protected function modifyTableAliasAndJoinNamesInDbExpr(DbExpr $dbExpr): DbExpr
    {
        $tableAlias = $this->getTableAlias();
        $replaces = ["%`{$tableAlias}`\.%" => '`' . $this->getShortJoinAlias($tableAlias) . '`.'];
        foreach ($this->joins as $joinConfig) {
            $joinName = $joinConfig->getJoinName();
            $replaces["%`{$joinName}`\.%"] = '`' . $this->getShortJoinAlias($joinName) . '`.';
        }
        foreach ($this->crossJoins as $joinConfig) {
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
        /**
         * @var string|int $columnAlias
         * @var string|array $columnName
         */
        foreach ($columns as $columnAlias => $columnName) {
            if ($columnAlias === '*') {
                if (!is_string($columnName) && !is_array($columnName)) {
                    throw new \InvalidArgumentException(
                        "Invalid excluded columns list for a key '$columnAlias'. "
                        . 'Value must be a string or array.'
                    );
                }
                // we're good to go - no more validation needed
            } elseif (
                !is_numeric($columnAlias)
                && (
                    is_array($columnName)
                    || $columnName === '*'
                )
            ) {
                $this->resolveColumnsToBeSelectedForJoin($columnAlias, $columnName, $joinName, false);
                continue;
            } elseif (!is_string($columnName) && !($columnName instanceof DbExpr)) {
                throw new \InvalidArgumentException(
                    "Invalid column name for a key '$columnAlias'. "
                    . '$columns argument must contain only strings and instances of DbExpr class.'
                );
            } elseif (empty($columnName)) {
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
            if ($columnName === '*' || $columnName === '**') {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $normalizedColumns = array_merge(
                    $normalizedColumns,
                    $this->normalizeWildcardColumn($joinName, [], $columnName === '**')
                );
            } elseif ($columnAlias === '*' || $columnAlias === '**') {
                /** @noinspection SlowArrayOperationsInLoopInspection */
                $normalizedColumns = array_merge(
                    $normalizedColumns,
                    $this->normalizeWildcardColumn($joinName, (array)$columnName, $columnAlias === '**')
                );
            } else {
                $columnInfo = $this->analyzeColumnName($columnName, $columnAlias, $joinName, $subject);
                if ($columnInfo['join_name'] !== $joinName && !isset($columnInfo['parent'])) {
                    // Note: ($joinName === null) restricts situation like
                    // new JoinConfig('Join2')->setForeignColumnsToSelect(['SomeOtehrJoin.col'])
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
     * @param bool $includeHeavyColumns - used in OrmSelect
     * @return array - returns list of $this->analyzeColumnName() results
     */
    protected function normalizeWildcardColumn(
        ?string $joinName = null,
        ?array $excludeColumns = null,
        bool $includeHeavyColumns = false
    ): array {
        return [$this->analyzeColumnName('*', null, $joinName, 'SELECT')];
    }

    /**
     * Decide what to do if join name mentioned in columns list
     * @param string $joinName
     * @param string|array $columns - string === '*' only
     * @param string|null $parentJoinName
     * @param bool $appendColumnsToExisting - true: $columns will be appended | false: $columns will replace existing ones
     * @throws \UnexpectedValueException
     */
    protected function resolveColumnsToBeSelectedForJoin(
        string $joinName,
        string|array $columns,
        ?string $parentJoinName = null,
        bool $appendColumnsToExisting = false
    ): void {
        throw new \UnexpectedValueException(
            "You must use JoinConfig->setForeignColumnsToSelect() to set the columns list to select for join named '{$joinName}'"
        );
    }

    /**
     * @param array $conditions
     * @param string $subject - can be 'WHERE', 'HAVING' or ''
     * @param null|string $joinName - string: used when assembling conditions for join
     * @return string
     */
    protected function makeConditions(array $conditions, string $subject = 'WHERE', ?string $joinName = null): string
    {
        $assembled = QueryBuilderUtils::assembleWhereConditionsFromArray(
            $this->getConnection(),
            $conditions,
            function ($columnName) use ($joinName, $subject) {
                return $this->columnQuoterForConditions($columnName, $joinName, $subject);
            },
            'AND',
            function ($columnName, $rawValue) {
                if ($rawValue instanceof DbExpr) {
                    return $this->quoteDbExpr($rawValue);
                }

                return $rawValue;
            }
        );
        $assembled = trim($assembled);
        return empty($assembled) ? '' : " {$subject} {$assembled}";
    }

    /**
     * @param string|DbExpr $columnName
     * @param string|null $joinName
     * @param string $subject - 'WHERE', 'HAVING', etc. - the part of a query we are qouting the column for
     * @return string
     */
    protected function columnQuoterForConditions(DbExpr|string $columnName, ?string $joinName, string $subject): string
    {
        $columnInfo = $this->analyzeColumnName($columnName, null, $joinName, $subject);
        if (!empty($columnInfo['join_name']) && in_array($subject, ['WHERE', 'HAVING'], true)) {
            $joins = explode('.', $columnInfo['join_name']);
            foreach ($joins as $subJoinName) {
                $this->joinsUsedInWhereAndHavingConditions[] = $subJoinName;
            }
            $columnInfo['join_name'] = $joins[count($joins) - 1];
        }
        return $this->makeColumnNameForCondition($columnInfo, $subject);
    }

    protected function makeGroupBy(): string
    {
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

    protected function makeOrderBy(): string
    {
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

    protected function makeJoins(bool $ignoreLeftJoins = false): string
    {
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
        foreach ($this->crossJoins as $joinConfig) {
            $joinQuery = $this->quoteDbExpr($joinConfig->getJoinQuery());
            $joins[] = "CROSS JOIN {$joinQuery} AS " . $this->quoteDbEntityName($this->getShortJoinAlias($joinConfig->getJoinName()));
        }
        return count($joins) ? ' ' . implode(' ', $joins) : '';
    }

    protected function isJoinUsedInWhereOrHavingConditions(NormalJoinConfigInterface $joinConfig): bool
    {
        return (
            in_array($this->getShortJoinAlias($joinConfig->getJoinName()), $this->joinsUsedInWhereAndHavingConditions, true)
            || in_array($joinConfig->getJoinName(), $this->joinsUsedInWhereAndHavingConditions, true)
        );
    }

    protected function makeJoinConditions(NormalJoinConfigInterface $joinConfig): string
    {
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

    protected function makeWithQueries(): string
    {
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
     */
    protected function replaceWithQueries(array $withQueries): static
    {
        $this->with = $withQueries;
        $this->setDirty('with');
        return $this;
    }

    /**
     * @param bool $itIsWithQuery - true: building a query for WITH
     * @return string
     * @throws \UnexpectedValueException
     */
    protected function makeColumnsForQuery(bool $itIsWithQuery = false): string
    {
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

    protected function makeDistinctForQuery(): string
    {
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
    protected function collectJoinedColumnsForQuery(bool $itIsWithQuery = false): array
    {
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

    protected function makeLimit(): string
    {
        return $this->limit > 0 ? ' LIMIT ' . $this->limit : '';
    }

    protected function makeOffset(): string
    {
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
    private function normalizeRecord(array $record, array $shortColumnAliasToAlias, array $shortJoinAliasToAlias): array
    {
        if (empty($record)) {
            return [];
        }
        $dataBlocks = [$this->getTableAlias() => []];
        // process record's column aliases and group column values by table alias
        foreach ($record as $columnAlias => $value) {
            if (isset($this->columnAliasToColumnInfo[$columnAlias])) {
                $colInfo = $this->columnAliasToColumnInfo[$columnAlias];
                $group = $colInfo['parent'] ?? $colInfo['join_name'];
                $dataBlocks[$group ?: $this->getTableAlias()][$colInfo['alias'] ?: $colInfo['name']] = $value;
            } elseif (preg_match('%^_(.+?)__(.+?)$%', $columnAlias, $colInfo)) {
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
        foreach (array_merge($this->joins, $this->crossJoins) as $joinConfig) {
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
            } elseif (count($joinConfig->getForeignColumnsToSelect()) > 0) {
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
     */
    protected function placeDataOfDeepNestedJoinsIntoRecord(array $joins, array &$data): void
    {
        /** @var JoinConfig[] $usedJoins */
        $usedJoins = [];
        foreach ($joins as $index => $join) {
            /** @var JoinConfig $config */
            $config = $join['config'];
            if (array_key_exists($config->getTableAlias(), $data)) {
                $data[$config->getTableAlias()][$config->getJoinName()] = $join['data'];
                $usedJoins[] = $config;
                unset($joins[$index]);
            }
        }
        if (count($usedJoins) > 0) {
            foreach ($usedJoins as $config) {
                if (count($joins) > 0 && isset($data[$config->getTableAlias()])) {
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
     */
    protected function normalizeJoinDataForRecord(NormalJoinConfigInterface $joinConfig, array $data): array
    {
        return $data;
    }

    protected function quoteDbExpr(DbExpr $expr): string
    {
        return $this->getConnection()
            ->quoteDbExpr($this->modifyTableAliasAndJoinNamesInDbExpr($expr));
    }

    protected function quoteDbEntityName(string $name): string
    {
        return $this->getConnection()
            ->quoteDbEntityName($name);
    }

    /**
     * Validate if there are all joins defined for all table aliases used in query
     * @throws \UnexpectedValueException
     */
    protected function validateIfThereAreEnoughJoins(): void
    {
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
                'Select does not have joins with next names: ' . implode(', ', $missingJoins)
            );
        }
    }

    //    abstract public function join(NormalJoinConfigAbstract $joinInfo, bool $append = true); //< it is impossible to overwrite method to use child class instead of NormalJoinConfigAbstract

    /**
     * @param string $alias
     * @param bool $mayBeShort - true: alias may be received from $this->getShortJoinAlias()
     * @return bool
     */
    protected function hasWithQuery(string $alias, bool $mayBeShort = false): bool
    {
        return isset($this->with[$alias]) || ($mayBeShort && in_array($alias, $this->shortJoinAliases, true));
    }

    /**
     * @param string $joinName
     * @param bool $mayBeShort - true: alias may be received from $this->getShortJoinAlias()
     * @return bool
     */
    protected function hasJoin(string $joinName, bool $mayBeShort = false): bool
    {
        return (
            isset($this->joins[$joinName])
            || isset($this->crossJoins[$joinName])
            || (
                $mayBeShort
                && in_array($joinName, $this->shortJoinAliases, true)
            )
        );
    }

    /**
     * @throws \UnexpectedValueException
     */
    protected function getJoin(string $joinName): NormalJoinConfigInterface|CrossJoinConfigInterface
    {
        if (!$this->hasJoin($joinName, true)) {
            throw new \UnexpectedValueException("Join config with name [{$joinName}] not found");
        }

        if (isset($this->joins[$joinName])) {
            return $this->joins[$joinName];
        }

        if (isset($this->crossJoins[$joinName])) {
            return $this->crossJoins[$joinName];
        }

        $alias = array_flip($this->shortJoinAliases)[$joinName];
        return $this->joins[$alias] ?: $this->crossJoins[$alias];
    }

    public function __clone()
    {
        foreach ($this->joins as $key => $joinConfig) {
            $this->joins[$key] = clone $joinConfig;
        }
        foreach ($this->crossJoins as $key => $joinConfig) {
            $this->crossJoins[$key] = clone $joinConfig;
        }
    }
}
