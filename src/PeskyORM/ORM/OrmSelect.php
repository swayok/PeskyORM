<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\AbstractJoinInfo;
use PeskyORM\Core\AbstractSelect;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;

class OrmSelect extends AbstractSelect
{
    
    /**
     * @var TableInterface
     */
    protected $table;
    /**
     * @var string
     */
    protected $tableAlias;
    /**
     * @var TableStructure
     */
    protected $tableStructure;
    /**
     * DB Record class (when null - $this->table->newRecord() will be used in $this->getNewRecord())
     * @var string|null
     */
    protected $recordClass;
    /**
     * @var array
     */
    protected $columnsToSelectFromJoinedRelations = [];
    /**
     * @var array
     */
    protected $joinNameToRelationName = [];
    /**
     * @var array
     */
    protected $joinedRelationsParents = [];
    /**
     * @var array
     */
    protected $joinsAddedByRelations = [];
    
    /**
     * @param TableInterface $table
     * @param string|null $tableAlias - used for relations / alias for table in case if it is not $table::getAlias()
     * @return static
     */
    static public function from(TableInterface $table, ?string $tableAlias = null)
    {
        return new static($table);
    }
    
    /**
     * @param TableInterface $table - table name or Table object
     * @param string|null $tableAlias - used for relations / alias for table in case if it is not $table::getAlias()
     */
    public function __construct(TableInterface $table, ?string $tableAlias = null)
    {
        $this->tableStructure = $table::getStructure();
        $this->table = $table;
        $this->setTableAlias(
            $tableAlias
                ?: $this->getTable()
                ->getAlias()
        );
    }
    
    public function getTableName(): string
    {
        return $this->getTableStructure()
            ->getTableName();
    }
    
    public function setTableAlias(string $tableAlias)
    {
        $this->tableAlias = $tableAlias;
        return $this;
    }
    
    public function getTableAlias(): string
    {
        return $this->tableAlias;
    }
    
    public function getTableSchemaName(): ?string
    {
        return $this->getTableStructure()
            ->getSchema();
    }
    
    public function getConnection(): DbAdapterInterface
    {
        return $this->getTable()
            ->getConnection(false);
    }
    
    public function getTable(): TableInterface
    {
        return $this->table;
    }
    
    public function setRecordClass(?string $class)
    {
        $this->recordClass = $class;
        return $this;
    }
    
    public function getNewRecord()
    {
        return $this->recordClass ? new $this->recordClass() : $this->table->newRecord();
    }
    
    public function getTableStructure(): TableStructure
    {
        return $this->tableStructure;
    }
    
    /**
     * @return Record|RecordInterface
     */
    public function fetchOneAsDbRecord(): RecordInterface
    {
        return $this->getNewRecord()
            ->fromDbData($this->fetchOne());
    }
    
    /**
     * @param OrmJoinInfo $joinInfo
     * @param bool $append
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function join(OrmJoinInfo $joinInfo, bool $append = true)
    {
        $this->_join($joinInfo, $append);
        return $this;
    }
    
    public function getCountQuery(bool $ignoreLeftJoins = true): string
    {
        if ($this->distinct) {
            $pkColumnName = $this->tableStructure->getPkColumnName();
            if (empty($this->distinctColumns) || in_array($pkColumnName, $this->distinctColumns, true)) {
                $columnInfo = $this->analyzeColumnName($pkColumnName);
            } else {
                $columnInfo = null;
                foreach ($this->distinctColumns as $distinctColumnInfo) {
                    if ($distinctColumnInfo['join_name'] === null && $distinctColumnInfo['json_selector'] === null) {
                        if (!$columnInfo) {
                            $columnInfo = $distinctColumnInfo;
                        } elseif ($distinctColumnInfo['name'] === $pkColumnName) {
                            $columnInfo = $distinctColumnInfo;
                            break;
                        }
                    }
                }
                if (empty($columnInfo)) {
                    $columnInfo = $this->distinctColumns[0];
                }
                $columnInfo['alias'] = null;
                $columnInfo['type_cast'] = null;
            }
            $expression = 'COUNT(DISTINCT ' . $this->makeColumnNameForCondition($columnInfo, 'DISTINCT') . ')';
            return $this->getSimplifiedQuery($expression, $ignoreLeftJoins, true);
        } else {
            return parent::getCountQuery($ignoreLeftJoins);
        }
    }
    
    /* ------------------------------------> SERVICE METHODS <-----------------------------------> */
    
    protected function normalizeJoinDataForRecord(AbstractJoinInfo $joinConfig, array $data): array
    {
        $data = parent::normalizeJoinDataForRecord($joinConfig, $data);
        if ($joinConfig instanceof OrmJoinInfo) {
            $pkName = $joinConfig->getForeignDbTable()
                ->getPkColumnName();
            if (array_key_exists($pkName, $data) && $data[$pkName] === null) {
                // not existing related record
                return [];
            }
        }
        return $data;
    }
    
    protected function beforeQueryBuilding()
    {
        if ($this->isDirty('joins') || $this->isDirty('with')) {
            $this->setDirty('columns');
            $this->setDirty('distinct');
            $this->setDirty('where');
            $this->setDirty('having');
            $this->setDirty('orderBy');
            $this->setDirty('groupBy');
        }
        if (
            $this->isDirty('columns')
            || $this->isDirty('distinct')
            || $this->isDirty('where')
            || $this->isDirty('having')
            || $this->isDirty('orderBy')
            || $this->isDirty('groupBy')
        ) {
            // remove all joins that were added to OrmSelect via indirect usage of relations
            foreach ($this->joinsAddedByRelations as $joinName) {
                unset($this->joins[$joinName]);
            }
            // clean all relations-joins properties
            $this->joinedRelationsParents = [];
            $this->columnsToSelectFromJoinedRelations = [];
            $this->joinNameToRelationName = [];
            $this->joinsAddedByRelations = [];
        }
        parent::beforeQueryBuilding();
    }
    
    protected function processRawColumns()
    {
        parent::processRawColumns();
        foreach ($this->columnsToSelectFromJoinedRelations as $joinName => $columns) {
            try {
                $this->getJoin($joinName)
                    ->setForeignColumnsToSelect(empty($columns) ? [] : $columns);
            } catch (\InvalidArgumentException $exc) {
                throw new \UnexpectedValueException('SELECT: ' . $exc->getMessage(), 0);
            }
        }
        return $this;
    }
    
    protected function normalizeWildcardColumn(?string $joinName = null, ?array $excludeColumns = null, bool $includeHeavyColumns = false): array
    {
        if ($joinName === null) {
            $tableStructure = $this->getTableStructure();
        } else {
            $tableStructure = $this->getJoin($joinName)
                ->getForeignDbTable()
                ->getTableStructure();
        }
        $normalizedColumns = [];
        if (!$excludeColumns) {
            $excludeColumns = [];
        }
        foreach ($tableStructure::getColumnsThatExistInDb() as $columnName => $config) {
            if (($includeHeavyColumns || !$config->isValueHeavy()) && !in_array($columnName, $excludeColumns, true)) {
                $normalizedColumns[] = $this->analyzeColumnName($columnName, null, $joinName, 'SELECT');
            }
        }
        return $normalizedColumns;
    }
    
    protected function resolveColumnsToBeSelectedForJoin(
        string $joinName,
        $columns,
        ?string $parentJoinName = null,
        bool $appendColumnsToExisting = false
    ) {
        if (is_array($columns) && !empty($columns)) {
            $filteredColumns = [];
            foreach ($columns as $columnAlias => $columnName) {
                if ($columnAlias === '*' && empty($columnName)) {
                    // ['*' => []] situation - convert to select '*'
                    $columnName = $columnAlias;
                    $columnAlias = -1;
                }
                if ($columnAlias === '*') {
                    // all columns except those listed in $columnName
                    $filteredColumns = array_merge(
                        $filteredColumns,
                        array_diff(
                            array_keys(
                                $this->getJoin($joinName)
                                    ->getForeignDbTable()
                                    ->getTableStructure()
                                    ->getColumnsThatExistInDb()
                            ),
                            (array)$columnName
                        )
                    );
                } elseif (!is_int($columnAlias) && (is_array($columnName) || $columnName === '*')) {
                    // subrealtion
                    $this->resolveColumnsToBeSelectedForJoin($columnAlias, $columnName, $joinName, false);
                } elseif (preg_match('%^(.*)\.\*$%', $columnName, $matches)) {
                    // subrealtion like 'SubRel.*'
                    $this->resolveColumnsToBeSelectedForJoin($matches[1], '*', $joinName, false);
                } else {
                    $filteredColumns[$columnAlias] = $columnName;
                }
            }
        } else {
            $filteredColumns = empty($columns) ? [] : $columns;
        }
        $relationName = $joinName;
        // resolve 'JoinName as OtherName'
        if (preg_match('%\s*(.+)\s+AS\s+(.+)\s*%is', $joinName, $matches)) {
            [, $relationName, $joinName] = $matches;
        }
        $this->joinNameToRelationName[$joinName] = $relationName;
        if ($appendColumnsToExisting && isset($this->columnsToSelectFromJoinedRelations[$joinName])) {
            $this->columnsToSelectFromJoinedRelations[$joinName] = array_merge(
                $this->columnsToSelectFromJoinedRelations[$joinName],
                $filteredColumns
            );
        } else {
            $this->columnsToSelectFromJoinedRelations[$joinName] = $filteredColumns;
        }
        $this->joinedRelationsParents[$joinName] = $parentJoinName;
    }
    
    /**
     * @param string $joinName - 'Name' or 'Name.SubName'
     * @return OrmJoinInfo
     */
    protected function getJoin(string $joinName)
    {
        $joins = explode('.', $joinName);
        foreach ($joins as $subJoin) {
            if (
                !$this->hasJoin($subJoin, true)
                && (
                    array_key_exists($subJoin, $this->joinedRelationsParents) //< array_key_exists is correct
                    || $this->getTableStructure()
                        ->hasRelation($subJoin)
                )
            ) {
                $this->addJoinFromRelation($subJoin);
            }
        }
        return parent::getJoin($joins[count($joins) - 1]);
    }
    
    /**
     * Load relation and all its parents
     * @param string $joinName
     * @return $this
     * @throws \UnexpectedValueException
     */
    protected function addJoinFromRelation(string $joinName)
    {
        if (!$this->hasJoin($joinName, false)) {
            if (
                !array_key_exists($joinName, $this->joinedRelationsParents) //< array_key_exists is correct
                && $this->getTableStructure()
                    ->hasRelation($joinName)
            ) {
                // this may happen only when relation is used in WHERE or HAVING
                $this->joinedRelationsParents[$joinName] = null;
                $this->joinNameToRelationName[$joinName] = $joinName;
                $this->columnsToSelectFromJoinedRelations[$joinName] = [];
            }
            $relationName = $this->getRelationNameByJoinName($joinName);
            $parentJoinName = $this->joinedRelationsParents[$joinName];
            if ($parentJoinName === null) {
                // join on base table
                if ($this->getTableStructure()
                        ->getRelation($relationName)
                        ->getType() === Relation::HAS_MANY) {
                    throw new \UnexpectedValueException(
                        "Relation '{$relationName}' has type 'HAS MANY' and should not be used as JOIN (not optimal). "
                        . 'Select that records outside of OrmSelect.'
                    );
                }
                $joinConfig = $this->getTable()
                    ->getJoinConfigForRelation($relationName, $this->getTableAlias(), $joinName);
            } else {
                // join on other join
                $this->addJoinFromRelation($parentJoinName);
                $parentJoin = $this->getJoin($parentJoinName);
                $foreignTable = $parentJoin->getForeignDbTable();
                if ($foreignTable->getTableStructure()
                        ->getRelation($relationName)
                        ->getType() === Relation::HAS_MANY) {
                    throw new \UnexpectedValueException(
                        "Relation '{$relationName}' has type 'HAS MANY' and should not be used as JOIN (not optimal). "
                        . 'Select that records outside of OrmSelect.'
                    );
                }
                $joinConfig = $foreignTable::getJoinConfigForRelation($relationName, $parentJoin->getJoinName(), $joinName);
            }
            $this->joinsAddedByRelations[] = $joinName;
            $joinConfig->setForeignColumnsToSelect(
                empty($this->columnsToSelectFromJoinedRelations[$joinName])
                    ? []
                    : $this->columnsToSelectFromJoinedRelations[$joinName]
            );
            $this->join($joinConfig);
        }
        return $this;
    }
    
    /**
     * @param array $conditions
     * @param string $subject
     * @param null|string $joinName - string: used when assembling conditions for join
     * @return string
     * @throws \UnexpectedValueException
     */
    protected function makeConditions(array $conditions, string $subject = 'WHERE', ?string $joinName = null): string
    {
        $assembled = Utils::assembleWhereConditionsFromArray(
            $this->getConnection(),
            $conditions,
            function ($columnName) use ($joinName, $subject) {
                return $this->columnQuoterForConditions($columnName, $joinName, $subject);
            },
            'AND',
            function ($columnName, $rawValue) use ($subject, $joinName) {
                if ($rawValue instanceof DbExpr) {
                    return $this->quoteDbExpr($rawValue);
                } elseif ($rawValue instanceof AbstractSelect) {
                    return '(' . $rawValue->getQuery() . ')';
                } elseif (!($columnName instanceof DbExpr)) {
                    $columnInfo = $this->analyzeColumnName($columnName, null, $joinName, $subject);
                    if ($columnInfo['join_name'] === null) {
                        $column = $this->getTableStructure()
                            ->getColumn($columnInfo['name']);
                    } else {
                        $column = $this
                            ->getJoin($columnInfo['join_name'])
                            ->getForeignDbTable()
                            ->getStructure()
                            ->getColumn($columnInfo['name']);
                    }
                    if (!$columnInfo['json_selector'] && !$columnInfo['type_cast']) {
                        // in json selector there may be any type of value and type casting is responsibility of developer
                        if (is_array($rawValue)) {
                            foreach ($rawValue as $arrValue) {
                                $errors = $column->validateValue($arrValue, false, true);
                                if (!empty($errors)) {
                                    break;
                                }
                            }
                        } elseif ($rawValue !== null) {
                            $errors = $column->validateValue($rawValue, false, true);
                        }
                    }
                    if (!empty($errors)) {
                        throw new \UnexpectedValueException(
                            "Invalid {$subject} condition value provided for column [{$columnName}]. Value: "
                            . var_export($rawValue, true) . '; Errors: ' . implode('; ', $errors)
                        );
                    }
                }
                return $rawValue;
            }
        );
        $assembled = trim($assembled);
        return empty($assembled) ? '' : " {$subject} {$assembled}";
    }
    
    protected function makeColumnNameWithAliasForQuery(array $columnInfo, bool $itIsWithQuery = false): string
    {
        $this->validateColumnInfo($columnInfo, 'SELECT');
        return parent::makeColumnNameWithAliasForQuery($columnInfo, $itIsWithQuery);
    }
    
    protected function makeColumnNameForCondition(array $columnInfo, string $subject = 'WHERE'): string
    {
        $this->validateColumnInfoForCondition($columnInfo, $subject);
        return parent::makeColumnNameForCondition($columnInfo);
    }
    
    /**
     * @param string $joinName
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getRelationNameByJoinName(string $joinName): string
    {
        if (!isset($joinName, $this->joinNameToRelationName)) {
            throw new \InvalidArgumentException("There is no known relation for join named '{$joinName}'");
        }
        return $this->joinNameToRelationName[$joinName];
    }
    
    /**
     * @param array $columnInfo
     * @param string $subject - used in exceptions, can be 'SELECT', 'ORDER BY', 'GROUP BY', 'WHERE' or 'HAVING'
     * @throws \UnexpectedValueException
     */
    protected function validateColumnInfo(array $columnInfo, string $subject)
    {
        if ($columnInfo['name'] instanceof DbExpr) {
            if (
                $columnInfo['name']->isValidationAllowed()
                && preg_match_all('%`([a-zA-Z_][a-zA-Z_0-9]+)`\.`([a-zA-Z_][a-zA-Z_0-9]+)`(?!\.)%', $columnInfo['name']->get(), $matches)
            ) {
                foreach ($matches[1] as $index => $joinName) {
                    $this->validateColumnInfo(
                        $this->analyzeColumnName($matches[2][$index], null, $joinName, $subject),
                        $subject
                    );
                }
            }
        } else {
            if ($columnInfo['join_name'] === null) {
                $isValid = $this->getTableStructure()
                    ->hasColumn($columnInfo['name']);
                if (!$isValid) {
                    throw new \UnexpectedValueException(
                        "{$subject}: Column with name [{$columnInfo['name']}] not found in "
                        . get_class($this->getTableStructure())
                    );
                }
            } else {
                $join = $this->getJoin($columnInfo['join_name']);
                if (!($join instanceof CrossJoinInfo)) {
                    $foreignTableStructure = $join->getForeignDbTable()
                        ->getTableStructure();
                    $isValid = $columnInfo['name'] === '*' || $foreignTableStructure::hasColumn($columnInfo['name']);
                    if (!$isValid) {
                        throw new \UnexpectedValueException(
                            "{$subject}: Column with name [{$columnInfo['join_name']}.{$columnInfo['name']}] not found in "
                            . get_class($foreignTableStructure)
                        );
                    }
                }
            }
        }
    }
    
    protected function validateColumnInfoForCondition(array $columnInfo, string $subject)
    {
        $this->validateColumnInfo($columnInfo, $subject);
    }
    
}
