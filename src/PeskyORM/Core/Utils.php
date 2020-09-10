<?php

namespace PeskyORM\Core;

class Utils {

    const FETCH_ALL = 'all';
    const FETCH_FIRST = 'first';
    const FETCH_VALUE = 'value';
    const FETCH_COLUMN = 'column';

    /**
     * Get data from $statement according to required $type
     * @param \PDOStatement $statement
     * @param string $type = 'first', 'all', 'value', 'column'
     * @return array|string
     * @throws \InvalidArgumentException
     */
    static public function getDataFromStatement(\PDOStatement $statement, $type = self::FETCH_ALL) {
        $type = strtolower($type);
        if (!in_array($type, array(self::FETCH_COLUMN, self::FETCH_ALL, self::FETCH_FIRST, self::FETCH_VALUE), true)) {
            throw new \InvalidArgumentException("Unknown processing type [{$type}]");
        }
        if ($statement && $statement->rowCount() > 0) {
            switch ($type) {
                case self::FETCH_COLUMN:
                    return $statement->fetchAll(\PDO::FETCH_COLUMN);
                case self::FETCH_VALUE:
                    return $statement->fetchColumn(0);
                case self::FETCH_FIRST:
                    return $statement->fetch(\PDO::FETCH_ASSOC);
                case self::FETCH_ALL:
                default:
                    return $statement->fetchAll(\PDO::FETCH_ASSOC);
            }
        } else if ($type === self::FETCH_VALUE) {
            return null;
        } else {
            return [];
        }
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
     *      function (string $columnName, DbAdapterInterface $connection) {
     *          return static::analyzeAndQuoteColumnName($columnName, $connection);
     *      }
     *      Note: $columnName here is expected to be string, DbExpr is not allowed here (and actually is not possible
     *          because object cannot be used as keys in associative arrays)
     * @param \Closure|null $conditionValuePreprocessor - used to modify or validate condition's value. Default:
     *      function (?string $columnName, $rawValue, DbAdapterInterface $connection) {
     *          return static::preprocessConditionValue($rawValue, $connection);
     *      }
     *      Note: $rawValue may be DbExpr or AbstractSelect instance but not any other object
     *      Note: $columnName usually is null when $rawValue is DbExpr or AbstractSelect instance
     * @return string
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function assembleWhereConditionsFromArray(
        DbAdapterInterface $connection,
        array $conditions,
        \Closure $columnQuoter = null,
        $glue = 'AND',
        \Closure $conditionValuePreprocessor = null
    ) {
        $glue = strtoupper(trim($glue));
        if (!in_array($glue, ['AND', 'OR'], true)) {
            throw new \InvalidArgumentException('$glue argument must be "AND" or "OR" (only in upper case)');
        }
        if (empty($conditions)) {
            return '';
        } else {
            if (!$columnQuoter) {
                $columnQuoter = function (string $columnName, DbAdapterInterface $connection) {
                    return static::analyzeAndQuoteColumnNameForCondition($columnName, $connection);
                };
            }
            if (!$conditionValuePreprocessor) {
                $conditionValuePreprocessor = function (?string $columnName, $rawValue, DbAdapterInterface $connection) {
                    return static::preprocessConditionValue($rawValue, $connection);
                };
            }
            $assembled = [];
            foreach ($conditions as $column => $rawValue) {
                $valueIsDbExpr = $valueIsSubSelect = false;
                if (is_object($rawValue)) {
                    $valueIsDbExpr = $rawValue instanceof DbExpr;
                    $valueIsSubSelect = $rawValue instanceof AbstractSelect;
                    if (!$valueIsDbExpr && !$valueIsSubSelect) {
                        throw new \InvalidArgumentException(
                            '$conditions argument may contain only objects of class DbExpr or AbstractSelect. Other objects are forbidden. Key: ' . (string)$column
                        );
                    } else if ($valueIsSubSelect && is_numeric($column)) {
                        throw new \InvalidArgumentException(
                            '$conditions argument may contain objects of class AbstractSelect only with non-numeric keys. Key: ' . (string)$column
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
                } else if (
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
                    $operator = $connection->convertConditionOperator($operator, $rawValue);
                    $assembled[] = $connection->assembleCondition(
                        $columnQuoter($column, $connection),
                        $operator,
                        $conditionValuePreprocessor($column, $rawValue, $connection),
                        $valueIsDbExpr || $valueIsSubSelect
                    );
                }
            }
            return implode(" $glue ", $assembled);
        }
    }
    
    /**
     * @param DbAdapterInterface $connection
     * @param string $columnName
     * @param null|string $columnAlias
     * @param null|string $joinName
     * @return array = [
     *      'name' => string,
     *      'alias' => ?string,
     *      'join_name' => ?string,
     *      'type_cast' => ?string,
     * ]
     */
    static public function analyzeColumnName(
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
        unset($columnName, $joinName, $columnAlias); //< to prevent faulty usage
    
        if ($ret['name'] === '*') {
            $ret['type_cast'] = null;
            $ret['alias'] = null;
            $ret['json_selector'] = null;
        } else if (!$connection->isValidDbEntityName($ret['json_selector'] ?: $ret['name'], true)) {
            if ($ret['json_selector']) {
                throw new \InvalidArgumentException('Invalid json selector: [' . $ret['json_selector'] . ']');
            } else {
                throw new \InvalidArgumentException('Invalid column name: [' . $ret['name'] . ']');
            }
        }
        /*
        [
            'name' => string,
            'alias' => ?string,
            'join_name' => ?string,
            'type_cast' => ?string,
            'json_selector' => ?string,
        ]
         */
        return $ret;
    }
    
    static public function splitColumnName(string $columnName): array {
        $typeCast = null;
        $columnAlias = null;
        $joinName = null;
        $jsonSelector = null;
        if (preg_match('%^\s*(.*?)\s+AS\s+(.+)$%is', $columnName, $aliasMatches)) {
            // 'col1 as alias1' or 'JoinName.col2 AS alias2' or 'JoinName.col3::datatype As alias3'
            [, $columnName, $columnAlias] = $aliasMatches;
        }
        if (preg_match('%^\s*(.*?)\s*::\s*([a-zA-Z0-9 _]+(?:\s*\([^)]+\))?)\s*$%is', $columnName, $dataTypeMatches)) {
            // 'col1::datatype' or 'JoinName.col2::datatype' or 'col3::data_type' or 'col4::data type' or 'col4::data_type()'
            [, $columnName, $typeCast] = $dataTypeMatches;
        }
        $columnName = trim($columnName);
        if (preg_match('%^\s*(.*?)\s*([-#]>.*)$%', $columnName, $jsonSelectorMatches)) {
            // json_column->key or json_column->>key or json_column->key->>key
            // or json_column#>key or json_column#>>key and so on
            [$jsonSelector, $columnName] = $jsonSelectorMatches;
        }
        if (preg_match('%^(\w+)\.(\w+|\*)$%', $columnName, $columnParts)) {
            // 'JoinName.column' or 'JoinName.*'
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
    
    /**
     * @param string $columnName
     * @param DbAdapterInterface $connection
     * @return string
     */
    static public function analyzeAndQuoteColumnNameForCondition(string $columnName, DbAdapterInterface $connection): string {
        $columnInfo = static::analyzeColumnName($connection, $columnName, null, null);
        $quotedTableAlias = '';
        if ($columnInfo['join_name']) {
            $quotedTableAlias = $connection->quoteDbEntityName($columnInfo['join_name']) . '.';
        }
        $columnName = $quotedTableAlias . $connection->quoteDbEntityName($columnInfo['json_selector'] ?: $columnInfo['name']);
        if ($columnInfo['type_cast']) {
            $columnName = $connection->addDataTypeCastToExpression($columnInfo['type_cast'], $columnName);
        }
        return $columnName;
    }
    
    /**
     * @param string $columnName
     * @param mixed|DbExpr|AbstractSelect $rawValue
     * @param DbAdapterInterface $connection
     * @return mixed - in most cases it is string
     */
    static public function preprocessConditionValue($rawValue, DbAdapterInterface $connection) {
        if ($rawValue instanceof DbExpr) {
            return $connection->quoteDbExpr($rawValue);
        } else if ($rawValue instanceof AbstractSelect) {
            return '(' . $rawValue->getQuery() . ')';
        } else {
            return $rawValue;
        }
    }
}