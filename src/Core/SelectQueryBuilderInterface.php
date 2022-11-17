<?php

declare(strict_types=1);

namespace PeskyORM\Core;

interface SelectQueryBuilderInterface
{

    public const ORDER_DIRECTION_ASC = 'asc';
    public const ORDER_DIRECTION_ASC_NULLS_FIRST = 'asc nulls first';
    public const ORDER_DIRECTION_ASC_NULLS_LAST = 'asc nulls last';
    public const ORDER_DIRECTION_DESC = 'desc';
    public const ORDER_DIRECTION_DESC_NULLS_FIRST = 'desc nulls first';
    public const ORDER_DIRECTION_DESC_NULLS_LAST = 'desc nulls last';

    public function getTableName(): string;

    public function getTableAlias(): string;

    public function getTableSchemaName(): ?string;

    public function getConnection(): DbAdapterInterface;

    /**
     * Build query from passed array
     * @param array $conditionsAndOptions - list of conditions and special keys:
     *      'COLUMNS' - list of columns to select, array or '*'
     *      'ORDER' - ORDER BY, array ['col1_name' => 'desc', 'col2_name', DbExpr::create('RAND()')]
     *      'GROUP' - GROUP BY, array ['col1_name', DbExpr::create('expression')]
     *      'LIMIT' - int >= 0; 0 - unlimited
     *      'OFFSET' - int >= 0
     *      'HAVING' - DbExpr,
     *      'JOINS' - array of JoinConfig
     */
    public function fromConfigsArray(array $conditionsAndOptions): static;

    public function fetchOne(): array;

    public function fetchMany(): array;

    public function fetchNextPage(): array;

    public function fetchPrevPage(): array;

    /**
     * Count records matching provided conditions and options
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return int
     */
    public function fetchCount(bool $ignoreLeftJoins = true): int;

    /**
     * Tests if there is at least 1 record matching provided conditions and options
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     * @return bool
     */
    public function fetchExistence(bool $ignoreLeftJoins = true): bool;

    public function fetchColumn(string|DbExpr $column): array;

    public function fetchAssoc(DbExpr|string $keysColumn, DbExpr|string $valuesColumn): array;

    public function fetchValue(DbExpr $expression): mixed;

    public function getQuery(): string;

    public function buildQueryToBeUsedInWith(): string;

    /**
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     */
    public function getCountQuery(bool $ignoreLeftJoins = true): string;

    /**
     * @param bool $ignoreLeftJoins - true: LEFT JOINs will be removed to count query (speedup for most cases)
     */
    public function getExistenceQuery(bool $ignoreLeftJoins = true): string;

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
     */
    public function columns(...$columns): static;

    /**
     * Set distinct flag to query (SELECT DISTINCT fields ...) or ((SELECT DISTINCT ON ($columns) fields)
     */
    public function distinct(bool $value = true, ?array $distinctColumns = null): static;

    /**
     * Set Conditions
     * May contain:
     * - DbExpr instances
     * - key-value pairs where key is column name with optional operator (ex: 'col_name !='), value may be
     * any of DbExpr, int, float, string, array. Arrays used for operators like 'IN', 'BETWEEN', '=' and other that
     * accept or require multiple values. Some operators are smart-converted to the ones that fit the value. For
     * example '=' with array value will be converted to 'IN', '!=' with NULL value will be converted to 'IS NOT'
     * You can group conditions inside 'AND' and 'OR' keys. ex: ['col3 => 0, 'OR' => ['col1' => 1, 'col2' => 2]]
     * will be assembled into ("col3" = 0 AND ("col1" = 1 OR "col2" => 2)).
     * By default - 'AND' is used if you group conditions into array without a key:
     * ['col3 => 0, ['col1' => 1, 'col2' => 2]] will be assembled into ("col3" = 0 AND ("col1" = 1 AND "col2" => 2))
     * @param array $conditions
     * @param bool $append
     * @return static
     * @see QueryBuilderUtils::assembleWhereConditionsFromArray() for more details about operators and features
     */
    public function where(array $conditions, bool $append = false): static;

    /**
     * Add ORDER BY
     * @param string|DbExpr $columnName - 'field1', 'JoinName.field1', DbExpr::create('RAND()')
     * @param bool|string $direction -
     *      'ASC' or true;
     *      'DESC' or false;
     *      Optional suffixes: 'NULLS LAST' or 'NULLS FIRST';
     *      Ignored if $columnName instance of DbExpr (use empty string as placeholder if requried)
     * @param bool $append - true: append to existing orders | false: replace existsing orders
     */
    public function orderBy(
        DbExpr|string $columnName,
        bool|string $direction = self::ORDER_DIRECTION_ASC,
        bool $append = true
    ): static;

    /**
     * Check if ordering by $columnName already exists
     */
    public function hasOrderingForColumn(DbExpr|string $columnName): bool;

    /**
     * Remove order by
     */
    public function removeOrdering(): static;

    public function getOrderByColumns(): array;

    /**
     * Add GROUP BY
     * @param array $columns - can contain 'col1' and 'ModelAlias.col1', DbExpr::create('expression')
     * @param bool $append - true: append to existing groups | false: replace existsing groups
     */
    public function groupBy(array $columns, bool $append = true): static;

    public function getGroupByColumns(): array;

    /**
     * Set/Remove LIMIT (0 = no limit)
     */
    public function limit(int $limit): static;

    public function getLimit(): int;

    /**
     * Set/Remove OFFSET (0 = no offset)
     */
    public function offset(int $offset): static;

    public function getOffset(): int;

    /**
     * Set LIMIT and OFFSET at once (0 = no limit / no offset)
     */
    public function page(int $limit, int $offset = 0): static;

    public function having(array $conditions): static;

    /**
     * @param SelectQueryBuilderInterface $select - a sub select that can be used as "table" in main select
     * @param string $selectName - alias for passed $select (access to the select will be available using this alias)
     * @param bool $append
     * @return static
     * @see https://www.postgresql.org/docs/current/queries-with.html
     */
    public function with(SelectQueryBuilderInterface $select, string $selectName, bool $append = true): static;

    /**
     * Add INNER/LEFT/RIGHT/FULL JOIN
     * @see https://www.postgresql.org/docs/current/queries-table-expressions.html#QUERIES-JOIN
     */
    public function join(NormalJoinConfigInterface $joinConfig, bool $append = true): static;

    /**
     * Add a CROSS JOIN to query
     * @see https://www.postgresql.org/docs/current/queries-table-expressions.html#QUERIES-JOIN
     */
    public function crossJoin(CrossJoinConfig $joinConfig, bool $append = true): static;

    public function __clone();
}