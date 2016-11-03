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
     * @param \Closure|null $columnQuoter - default: function ($columnName, DbAdapterInterface $connection) {
     *      return $connection->quoteDbEntityName($columnName);
     *  }
     * @param \Closure|null $conditionValuePreprocessor - used to modify or validate condition's value.
     *      default: function ($columnName, $rawValue, DbAdapterInterface $connection) {
     *          return ($rawValue instanceof DbExpr) ? $connection->quoteDbExpr($rawValue) : $rawValue;
     *      }
     *      Note: $rawValue can be DbExpr instance but not any other object
     * @return string
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
        if (!$columnQuoter) {
            $columnQuoter = function ($columnName, DbAdapterInterface $connection) {
                return $connection->quoteDbEntityName($columnName);
            };
        }
        if (!$conditionValuePreprocessor) {
            $conditionValuePreprocessor = function ($columnName, $rawValue, DbAdapterInterface $connection) {
                return ($rawValue instanceof DbExpr) ? $connection->quoteDbExpr($rawValue) : $rawValue;
            };
        }
        if (empty($conditions)) {
            return '';
        } else {
            $assembled = [];
            foreach ($conditions as $column => $rawValue) {
                if (is_object($rawValue) && !($rawValue instanceof DbExpr)) {
                    throw new \InvalidArgumentException(
                        '$conditions argument may contain only objects of class DbExpr. Other objects are forbidden.'
                    );
                }
                if (!is_numeric($column) && empty($column)) {
                    throw new \InvalidArgumentException('Empty column name detected in $conditions argument');
                }
                $valueIsDbExpr = is_object($rawValue) && ($rawValue instanceof DbExpr);
                if (is_numeric($column) && $valueIsDbExpr) {
                    // 1 - custom expressions
                    $assembled[] = $conditionValuePreprocessor($column, $rawValue, $connection);
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
                    $assembled[] = '(' . $subConditons . ')';
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
                        $valueIsDbExpr
                    );
                }
            }
            return implode(" $glue ", $assembled);
        }
    }
}