<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\AbstractJoinInfo;
use PeskyORM\Core\AbstractSelect;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;

class OrmSelect extends AbstractSelect {

    /**
     * @var TableInterface
     */
    protected $table;
    /**
     * @var TableStructure
     */
    protected $tableStructure;
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
     * @return static
     */
    static public function from(TableInterface $table) {
        return new static($table);
    }

    /**
     * @param TableInterface $table - table name or Table object
     */
    public function __construct(TableInterface $table) {
        $this->table = $table;
        $this->tableStructure = $table::getStructure();
    }

    public function getTableName(): string {
        return $this->getTableStructure()->getTableName();
    }

    public function getTableAlias(): string {
        return $this->getTable()->getAlias();
    }

    public function getTableSchemaName(): ?string {
        return $this->getTableStructure()->getSchema();
    }

    public function getConnection(): DbAdapterInterface {
        return $this->getTable()->getConnection(false);
    }

    public function getTable(): TableInterface {
        return $this->table;
    }

    public function getTableStructure(): TableStructure {
        return $this->tableStructure;
    }

    /**
     * @return Record|RecordInterface
     */
    public function fetchOneAsDbRecord(): RecordInterface {
        return $this->table->newRecord()->fromDbData($this->fetchOne());
    }

    /**
     * @param OrmJoinInfo $joinInfo
     * @param bool $append
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function join(OrmJoinInfo $joinInfo, bool $append = true) {
        $this->_join($joinInfo, $append);
        return $this;
    }

    /* ------------------------------------> SERVICE METHODS <-----------------------------------> */

    protected function normalizeJoinDataForRecord(AbstractJoinInfo $joinInfo, array $data): array {
        $data = parent::normalizeJoinDataForRecord($joinInfo, $data);
        if ($joinInfo instanceof OrmJoinInfo) {
            $pkName = $joinInfo->getForeignDbTable()->getPkColumnName();
            if (array_key_exists($pkName, $data) && $data[$pkName] === null) {
                 // not existing related record
                return [];
            }
        }
        return $data;
    }

    protected function beforeQueryBuilding() {
        if ($this->isDirty('joins') || $this->isDirty('with')) {
            $this->setDirty('columns');
            $this->setDirty('where');
            $this->setDirty('having');
            $this->setDirty('orderBy');
            $this->setDirty('groupBy');
        }
        if (
            $this->isDirty('columns')
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

    protected function processRawColumns() {
        parent::processRawColumns();
        foreach ($this->columnsToSelectFromJoinedRelations as $joinName => $columns) {
            try {
                $this->getJoin($joinName)->setForeignColumnsToSelect(empty($columns) ? [] : $columns);
            } catch (\InvalidArgumentException $exc) {
                throw new \UnexpectedValueException('SELECT: ' . $exc->getMessage(), 0);
            }
        }
        return $this;
    }

    protected function normalizeWildcardColumn(?string $joinName = null, ?array $excludeColumns = null): array {
        if ($joinName === null) {
            $tableStructure = $this->getTableStructure();
        } else {
            $tableStructure = $this->getJoin($joinName)->getForeignDbTable()->getTableStructure();
        }
        $normalizedColumns = [];
        if (!$excludeColumns) {
            $excludeColumns = [];
        }
        foreach ($tableStructure::getColumnsThatExistInDb() as $columnName => $config) {
            if (!$config->isValueHeavy() && !in_array($columnName, $excludeColumns, true)) {
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
            /** @var array $columns */
            foreach ($columns as $columnAlias => $columnName) {
                if (!is_int($columnAlias) && (is_array($columnName) || $columnName === '*')) {
                    // subrealtion
                    $this->resolveColumnsToBeSelectedForJoin($columnAlias, $columnName, $joinName, false);
                } else if (preg_match('%^(.*)\.\*$%', $columnName, $matches)) {
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
            list(, $relationName, $joinName) = $matches;
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
     * @param string $joinName
     * @return OrmJoinInfo
     */
    protected function getJoin(string $joinName) {
        if (
            !$this->hasJoin($joinName, true)
            && (
                array_key_exists($joinName, $this->joinedRelationsParents) //< array_key_exists is correct
                || $this->getTableStructure()->hasRelation($joinName)
            )
        ) {
            $this->addJoinFromRelation($joinName);
        }
        return parent::getJoin($joinName);
    }

    /**
     * Load relation and all its parents
     * @param string $joinName
     * @return $this
     * @throws \UnexpectedValueException
     */
    protected function addJoinFromRelation(string $joinName) {
        if (!$this->hasJoin($joinName, false)) {
            if (
                !array_key_exists($joinName, $this->joinedRelationsParents) //< array_key_exists is correct
                && $this->getTableStructure()->hasRelation($joinName)
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
                if ($this->getTableStructure()->getRelation($relationName)->getType() === Relation::HAS_MANY) {
                    throw new \UnexpectedValueException(
                        "Relation '{$relationName}' has type 'HAS MANY' and should not be used as JOIN (not optimal). "
                            . 'Select that records outside of OrmSelect.'
                    );
                }
                $joinConfig = $this->getTable()->getJoinConfigForRelation($relationName, $this->getTableAlias(), $joinName);
            } else {
                // join on other join
                $this->addJoinFromRelation($parentJoinName);
                $parentJoin = $this->getJoin($parentJoinName);
                $foreignTable = $parentJoin->getForeignDbTable();
                if ($foreignTable->getTableStructure()->getRelation($relationName)->getType() === Relation::HAS_MANY) {
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
    protected function makeConditions(array $conditions, string $subject = 'WHERE', ?string $joinName = null): string {
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
                } else if ($rawValue instanceof AbstractSelect) {
                    return '(' . $rawValue->getQuery() . ')';
                } else if (!($columnName instanceof DbExpr)) {
                    $columnInfo = $this->analyzeColumnName($columnName, null, $joinName, $subject);
                    if ($columnInfo['join_name'] === null) {
                        $column = $this->getTableStructure()->getColumn($columnInfo['name']);
                    } else {
                        $column = $this
                            ->getJoin($columnInfo['join_name'])
                            ->getForeignDbTable()
                            ->getStructure()
                            ->getColumn($columnInfo['name']);
                    }
                    if (is_array($rawValue)) {
                        foreach ($rawValue as $arrValue) {
                            $errors = $column->validateValue($arrValue, false, true);
                            if (!empty($errors)) {
                                break;
                            }
                        }
                    } else if ($rawValue !== null) {
                        $errors = $column->validateValue($rawValue, false, true);
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

    protected function makeColumnNameWithAliasForQuery(array $columnInfo, bool $itIsWithQuery = false): string {
        $this->validateColumnInfo($columnInfo, 'SELECT');
        return parent::makeColumnNameWithAliasForQuery($columnInfo, $itIsWithQuery);
    }

    protected function makeColumnNameForCondition(array $columnInfo, string $subject = 'WHERE'): string {
        $this->validateColumnInfo($columnInfo, $subject);
        return parent::makeColumnNameForCondition($columnInfo);
    }

    /**
     * @param string $joinName
     * @return string
     * @throws \InvalidArgumentException
     */
    protected function getRelationNameByJoinName(string $joinName): string {
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
    protected function validateColumnInfo(array $columnInfo, string $subject) {
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
                $isValid = $this->getTableStructure()->hasColumn($columnInfo['name']);
                if (!$isValid) {
                    throw new \UnexpectedValueException(
                        "{$subject}: Column with name [{$columnInfo['name']}] not found in "
                            . get_class($this->getTableStructure())
                    );
                }
            } else {
                $foreignTableStructure = $this
                    ->getJoin($columnInfo['join_name'])
                    ->getForeignDbTable()
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
