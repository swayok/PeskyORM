<?php

declare(strict_types=1);

namespace PeskyORM\Core\Utils;

use JetBrains\PhpStorm\ArrayShape;
use PeskyORM\Core\AbstractSelect;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;

abstract class QueryBuilderUtils
{
    // 'col1 as alias1' or 'JoinName.col2 AS alias2' or 'JoinName.col3::datatype As alias3'
    public const REGEXP_COLUMN_AS_ALIAS = '%^\s*(.*?)\s+AS\s+(.+)$%is';
    // 'col1::datatype' or 'JoinName.col2::datatype' or 'col3::data_type'
    // or 'col4::data type' or 'col4::data_type()'
    public const REGEXP_COLUMN_TYPE_CONVERSION = '%^\s*(.*?)\s*::\s*([a-zA-Z0-9 _]+(?:\s*\([^)]+\))?)\s*$%is';
    // json_column->key or json_column->>key or json_column->key->>key
    // or json_column#>key or json_column#>>key and so on
    public const REGEXP_COLUMN_JSON_SELECTOR = '%^\s*(.*?)\s*([-#]>.*)$%';
    // 'JoinName.column' or 'JoinName.*' or  'JoinName.SubjoinName.column' or 'JoinName.SubjoinName.*'
    public const REGEXP_COLUMN_PARTS = '%^([\w.]+)\.(\w+|\*)$%';

    public const QUERY_PART_WITH = 'WITH';
    public const QUERY_PART_CONTAINS = 'CONTAINS';
    public const QUERY_PART_JOINS = 'JOINS';
    public const QUERY_PART_DISTINCT = 'DISTINCT';
    public const QUERY_PART_ORDER = 'ORDER';
    public const QUERY_PART_LIMIT = 'LIMIT';
    public const QUERY_PART_OFFSET = 'OFFSET';
    public const QUERY_PART_GROUP = 'GROUP';
    public const QUERY_PART_HAVING = 'HAVING';

    /**
     * @param array $columns - should contain only strings and DbExpr objects
     * @param bool $withBraces - add "()" around columns list
     * @return string - "(`column1','column2',...)"
     */
    public static function buildColumnsList(
        DbAdapterInterface $adapter,
        array $columns,
        bool $withBraces = true
    ): string {
        $quoted = implode(
            ', ',
            array_map(static function ($column) use ($adapter) {
                return ($column instanceof DbExpr)
                    ? $adapter->quoteDbExpr($column)
                    : $adapter->quoteDbEntityName($column);
            }, $columns)
        );
        return $withBraces ? '(' . $quoted . ')' : $quoted;
    }

    /**
     * @param array $columns - expected set of columns
     * @param array $valuesAssoc - key-value array where keys = columns
     * @param array $dataTypes - key-value array where key = table column and value = data type for associated column
     * @param int $recordIdx - index of record (needed to make exception message more useful)
     * @return string - "('value1','value2',...)"
     * @throws \InvalidArgumentException
     */
    public static function buildValuesList(
        DbAdapterInterface $adapter,
        array $columns,
        array $valuesAssoc,
        array $dataTypes = [],
        int $recordIdx = 0
    ): string {
        $ret = [];
        if (empty($columns)) {
            throw new \InvalidArgumentException('$columns argument cannot be empty');
        }
        foreach ($columns as $column) {
            if (!array_key_exists($column, $valuesAssoc)) {
                throw new \InvalidArgumentException(
                    "\$valuesAssoc array does not contain key [$column]. Record index: $recordIdx. "
                    . 'Data: ' . print_r($valuesAssoc, true)
                );
            }
            $ret[] = $adapter->quoteValue(
                $valuesAssoc[$column],
                empty($dataTypes[$column]) ? null : $dataTypes[$column]
            );
        }
        return '(' . implode(', ', $ret) . ')';
    }

    /**
     * @param array $valuesAssoc - key-value array where keys are columns names
     * @param array $dataTypes - key-value array where keys are columns names and values are data type for associated column (\PDO::PARAM_*)
     * @return string - "col1" = 'val1', "col2" = 'val2'
     */
    public static function buildValuesListForUpdate(
        DbAdapterInterface $adapter,
        array $valuesAssoc,
        array $dataTypes = []
    ): string {
        $ret = [];
        foreach ($valuesAssoc as $column => $value) {
            $quotedValue = $adapter->quoteValue(
                $value,
                empty($dataTypes[$column]) ? null : $dataTypes[$column]
            );
            $ret[] = $adapter->quoteDbEntityName($column) . '=' . $quotedValue;
        }
        return implode(', ', $ret);
    }

    /**
     * we have next cases:
     * 1. $value is instance of DbExpr (converted to quoted string), $column - index
     *
     * 2. $column is string
     *     (results in 'colname' <operator> 'value')
     * 2.1 $column contains equation sign (for example 'colname >', results in 'colname' > 'value')
     *     Short list of equations:
     *          >, <, =, !=, >=, <=, LIKE, ~, ~*, !~, !~*, SIMILAR TO, IN; NOT; NOT IN; IS; IS NOT
     * 2.2 $value === null, $column may contain: 'NOT', '!=', '=', 'IS', 'IS NOT'
     *     (results in 'colname' IS NULL or 'colname' IS NOT NULL)
     * 2.3 $column contains 'BETWEEN' or 'NOT BETWEEN' and $value is array or string like 'val1 and val2'
     *     (results in 'colname' BETWEEN a and b)
     * 2.4 $column contains no equation sign or contains '!=' or 'NOT', $value is array or DbExpr
     *     (results in 'colname' IN (a,b,c) or 'colname' IN (SELECT '*' FROM ...))
     *
     * 3. $value === array()
     * 3.1. $column !== 'OR' -> recursion: $this->assembleConditions($value))
     * 3.2. $column === 'OR' -> recursion: $this->assembleConditions($value, 'OR'))
     *
     * @param DbAdapterInterface $connection
     * @param array $conditions
     * @param string $glue - 'AND' or 'OR'
     * @param \Closure|null $columnQuoter - used to quote column name. Default:
     *      function (string $columnName, DbAdapterInterface $connection): string {
     *          return static::analyzeAndQuoteColumnName($columnName, $connection);
     *      }
     *      Note: $columnName here is expected to be string, DbExpr is not allowed here (and actually is not possible
     *          because object cannot be used as keys in associative arrays)
     * @param \Closure|null $conditionValuePreprocessor - used to modify or validate condition's value. Default:
     *      function (?string $columnName, $rawValue, DbAdapterInterface $connection): string {
     *          return static::preprocessConditionValue($rawValue, $connection);
     *      }
     *      Note: $rawValue may be DbExpr or AbstractSelect instance but not any other object
     *      Note: $columnName usually is null when $rawValue is DbExpr or AbstractSelect instance
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function assembleWhereConditionsFromArray(
        DbAdapterInterface $connection,
        array $conditions,
        \Closure $columnQuoter = null,
        string $glue = 'AND',
        \Closure $conditionValuePreprocessor = null
    ): string {
        $glue = strtoupper(trim($glue));
        if (!in_array($glue, ['AND', 'OR'], true)) {
            throw new \InvalidArgumentException('$glue argument must be "AND" or "OR" (only in upper case)');
        }

        if (empty($conditions)) {
            return '';
        }

        if (!$columnQuoter) {
            $columnQuoter = static function (string $columnName, DbAdapterInterface $connection) {
                return static::analyzeAndQuoteColumnNameForCondition($columnName, $connection);
            };
        }

        if (!$conditionValuePreprocessor) {
            $conditionValuePreprocessor = static function (
                ?string $columnName,
                $rawValue,
                DbAdapterInterface $connection
            ) {
                if ($rawValue instanceof DbExpr) {
                    return $connection->quoteDbExpr($rawValue);
                }
                return $rawValue;
            };
        }

        $assembled = [];
        foreach ($conditions as $column => $rawValue) {
            $valueIsDbExpr = $rawValue instanceof DbExpr;

            if (is_object($rawValue)) {
                $valueIsSubSelect = $rawValue instanceof AbstractSelect;
                if (!$valueIsDbExpr && !$valueIsSubSelect) {
                    throw new \InvalidArgumentException(
                        '$conditions argument may contain only objects of class DbExpr or AbstractSelect. Other objects are forbidden. Key: ' . $column
                    );
                }

                if ($valueIsSubSelect && is_numeric($column)) {
                    throw new \InvalidArgumentException(
                        '$conditions argument may contain objects of class AbstractSelect only with non-numeric keys. Key: ' . $column
                    );
                }
            }

            if (!is_numeric($column) && empty($column)) {
                throw new \InvalidArgumentException('Empty column name detected in $conditions argument');
            }

            if (is_numeric($column) && $valueIsDbExpr) {
                // 1 - custom expressions
                $assembled[] = $conditionValuePreprocessor(null, $rawValue, $connection);
                continue;
            }

            if (
                (
                    is_numeric($column)
                    && is_array($rawValue)
                )
                || in_array(strtolower(trim($column)), ['and', 'or'], true)
            ) {
                // 3: 3.1 and 3.2 - recursion
                $subGlue = is_numeric($column) ? 'AND' : $column;
                $subConditons = static::assembleWhereConditionsFromArray(
                    $connection,
                    $rawValue,
                    $columnQuoter,
                    $subGlue,
                    $conditionValuePreprocessor
                );
                if (!empty($subConditons)) {
                    $assembled[] = '(' . $subConditons . ')';
                }
            } else {
                $operator = '=';
                // find and prepare operator
                $operators = [
                    '\s*(?:>|<|=|\!=|>=|<=|@>|<@|\?|\?\||\?\&|~|~\*|!~|!~*)',   //< basic operators
                    '\s+(?:.+?)$',           //< other operators
                ];
                $operatorsRegexp = '%^(.*?)(' . implode('|', $operators) . ')\s*$%i';
                if (preg_match($operatorsRegexp, $column, $matches)) {
                    // 2.1
                    if (trim($matches[1]) === '') {
                        throw new \InvalidArgumentException(
                            "Empty column name detected in \$conditions argument: $column"
                        );
                    }
                    $column = trim($matches[1]);
                    $operator = strtoupper(preg_replace('%\s+%', ' ', trim($matches[2])));
                }
                $assembled[] = $connection->assembleCondition(
                    $columnQuoter($column, $connection),
                    $operator,
                    $conditionValuePreprocessor($column, $rawValue, $connection),
                    $valueIsDbExpr
                );
            }
        }
        return implode(" $glue ", $assembled);
    }

    #[ArrayShape([
        'name' => "string",
        'alias' => "string|null",
        'join_name' => "string|null",
        'type_cast' => "string|null",
        'json_selector' => "string|null",
    ])]
    public static function analyzeColumnName(
        DbAdapterInterface $connection,
        string $columnName,
        ?string $columnAlias = null,
        ?string $joinName = null
    ): array {
        $columnName = trim($columnName);
        if ($columnName === '') {
            throw new \InvalidArgumentException('$columnName argument is not allowed to be an empty string');
        }
        if ($columnAlias !== null) {
            $columnAlias = trim($columnAlias);
            if ($columnAlias === '') {
                throw new \InvalidArgumentException('$columnAlias argument is not allowed to be an empty string');
            }
        }
        if ($joinName !== null) {
            $joinName = trim($joinName);
            if ($joinName === '') {
                throw new \InvalidArgumentException('$joinName argument is not allowed to be an empty string');
            }
        }
        $ret = static::splitColumnName($columnName);
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
        } elseif (!$connection->isValidDbEntityName($ret['json_selector'] ?: $ret['name'], true)) {
            if ($ret['json_selector']) {
                throw new \InvalidArgumentException('Invalid json selector: [' . $ret['json_selector'] . ']');
            }

            throw new \InvalidArgumentException('Invalid column name: [' . $ret['name'] . ']');
        }
        return $ret;
    }

    #[ArrayShape([
        'name' => "string",
        'alias' => "string|null",
        'join_name' => "string|null",
        'type_cast' => "string|null",
        'json_selector' => "string|null",
    ])]
    public static function splitColumnName(string $columnName): array
    {
        $typeCast = null;
        $columnAlias = null;
        $joinName = null;
        $jsonSelector = null;
        if (preg_match(static::REGEXP_COLUMN_AS_ALIAS, $columnName, $aliasMatches)) {
            // 'col1 as alias1' or 'JoinName.col2 AS alias2' or 'JoinName.col3::datatype As alias3'
            [, $columnName, $columnAlias] = $aliasMatches;
        }
        if (preg_match(static::REGEXP_COLUMN_TYPE_CONVERSION, $columnName, $dataTypeMatches)) {
            // 'col1::datatype' or 'JoinName.col2::datatype' or 'col3::data_type'
            // or 'col4::data type' or 'col4::data_type()'
            [, $columnName, $typeCast] = $dataTypeMatches;
        }
        $columnName = trim($columnName);
        if (preg_match(static::REGEXP_COLUMN_JSON_SELECTOR, $columnName, $jsonSelectorMatches)) {
            // json_column->key or json_column->>key or json_column->key->>key
            // or json_column#>key or json_column#>>key and so on
            [$jsonSelector, $columnName] = $jsonSelectorMatches;
        }
        if (preg_match(static::REGEXP_COLUMN_PARTS, $columnName, $columnParts)) {
            // 'JoinName.column' or 'JoinName.*' or  'JoinName.SubjoinName.column' or 'JoinName.SubjoinName.*'
            [, $joinName, $columnName] = $columnParts;
        }

        return [
            'name' => $columnName,
            'alias' => $columnAlias,
            'join_name' => $joinName,
            'type_cast' => $typeCast,
            'json_selector' => $jsonSelector //< full selector including column name
        ];
    }

    public static function analyzeAndQuoteColumnNameForCondition(
        string $columnName,
        DbAdapterInterface $connection
    ): string {
        $columnInfo = static::analyzeColumnName($connection, $columnName, null, null);
        $quotedTableAlias = '';
        if ($columnInfo['join_name']) {
            $quotedTableAlias = $connection->quoteDbEntityName($columnInfo['join_name']) . '.';
        }
        $columnName = $quotedTableAlias . $connection->quoteDbEntityName(
                $columnInfo['json_selector'] ?: $columnInfo['name']
            );
        if ($columnInfo['type_cast']) {
            $columnName = $connection->addDataTypeCastToExpression(
                $columnInfo['type_cast'],
                $columnName
            );
        }
        return $columnName;
    }

    public static function separateConditionsAndOptions(
        array $conditionsAndOptions,
        ?array $forbiddenOptions = null
    ): array {
        $options = [];

        $parts = [
            static::QUERY_PART_WITH,
            static::QUERY_PART_JOINS,
            static::QUERY_PART_DISTINCT,
            static::QUERY_PART_ORDER,
            static::QUERY_PART_LIMIT,
            static::QUERY_PART_OFFSET,
            static::QUERY_PART_GROUP,
            static::QUERY_PART_HAVING,
            static::QUERY_PART_CONTAINS,
        ];

        if (array_key_exists('JOIN', $conditionsAndOptions)) {
            $conditionsAndOptions[static::QUERY_PART_JOINS] = $conditionsAndOptions['JOIN'];
            unset($conditionsAndOptions['JOIN']);
        }

        if (array_key_exists('CONTAIN', $conditionsAndOptions)) {
            $conditionsAndOptions[static::QUERY_PART_CONTAINS] = $conditionsAndOptions['CONTAIN'];
            unset($conditionsAndOptions['CONTAIN']);
        }

        if ($forbiddenOptions) {
            $forbidden = array_intersect($forbiddenOptions, array_keys($conditionsAndOptions));
            if (!empty($forbidden)) {
                throw new \UnexpectedValueException(
                    '$conditionsAndOptions array cannot contain options: ' . implode(', ', $forbidden)
                );
            }
        }

        foreach ($parts as $part) {
            if (array_key_exists($part, $conditionsAndOptions)) {
                $options[$part] = $conditionsAndOptions[$part];
                unset($conditionsAndOptions[$part]);
            }
        }

        return [$conditionsAndOptions, $options];
    }
}