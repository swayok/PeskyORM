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
            return array();
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
     * @param \Closure $columnQuoter
     * @return string
     * @throws \PDOException
     * @throws \InvalidArgumentException
     */
    static public function assembleWhereConditionsFromArray(
        DbAdapterInterface $connection,
        array $conditions,
        $glue = 'AND',
        \Closure $columnQuoter
    ) {
        $glue = strtoupper(trim($glue));
        if (!in_array($glue, ['AND', 'OR'], true)) {
            throw new \InvalidArgumentException('$glue argument must be "AND" or "OR"');
        }
        if (empty($conditions)) {
            return '';
        } else {
            $assembled = [];
            foreach ($conditions as $column => $value) {
                if (is_object($value) && !($value instanceof DbExpr)) {
                    throw new \InvalidArgumentException(
                        '$conditions argument may contain objects of class DbExpr only. Other object are forbidden.'
                    );
                }
                $valueIsDbExpr = is_object($value) && ($value instanceof DbExpr);
                if (is_numeric($column) && $valueIsDbExpr) {
                    // 1 - custom expressions
                    $assembled[] = $connection->quoteDbExpr($value);
                    continue;
                } else if (
                    (
                        is_numeric($column)
                        && is_array($value)
                    )
                    || in_array(strtolower(trim($column)), ['and', 'or'], true)
                ) {
                    // 3: 3.1 and 3.2 - recursion
                    $subGlue = is_numeric($column) ? 'AND' : $column;
                    $subConditons = static::assembleWhereConditionsFromArray(
                        $connection,
                        $value,
                        $subGlue,
                        $columnQuoter
                    );
                    $assembled[] = '(' . $subConditons . ')';
                } else {
                    $operator = '=';
                    // find and prepare operator
                    $operators = [
                        '\s*(?:>|<|=|\!=|>=|<=)',   //< basic operators
                        '\s+(?:.+?)\s*$',           //< other operators
                    ];
                    $operatorsRegexp = '%^' . implode('|', $operators) . '\s*$%i';
                    if (preg_match($operatorsRegexp, $column, $matches)) {
                        // 2.1
                        if (trim($matches[1]) !== '') {
                            throw new \InvalidArgumentException(
                                "Empty column name detected in \$conditions argument: $column"
                            );
                        }
                        $column = trim($matches[1]);
                        $operator = strtoupper(preg_replace('%\s+%', ' ', trim($matches[2])));
                    }
                    $operator = $connection->convertConditionOperator($operator, $value);
                    if ($column instanceof DbExpr) {
                        $column = $connection->quoteDbExpr($column);
                    } else {
                        $column = $columnQuoter($column);
                    }
                    $value = $connection->assembleConditionValue($value, $operator);
                    $assembled[] = "{$column} {$operator} {$value}";
                }
            }
            return implode(" $glue ", $assembled);
        }
    }
}