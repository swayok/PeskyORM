<?php

declare(strict_types=1);

namespace PeskyORM\Select;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\DbExpr;
use PeskyORM\Join\CrossJoinConfigInterface;
use PeskyORM\Join\NormalJoinConfigInterface;
use PeskyORM\Join\OrmJoinConfigInterface;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Table\TableInterface;
use PeskyORM\ORM\TableStructure\RelationInterface;
use PeskyORM\ORM\TableStructure\TableStructureInterface;
use PeskyORM\Utils\QueryBuilderUtils;

class OrmSelect extends SelectQueryBuilderAbstract implements OrmSelectQueryBuilderInterface
{
    protected TableInterface $table;
    protected TableStructureInterface $tableStructure;
    /**
     * DB Record class (when null - $this->table->newRecord() will be used in $this->getNewRecord())
     */
    protected ?string $recordClass = null;
    protected array $columnsToSelectFromJoinedRelations = [];
    protected array $joinNameToRelationName = [];
    protected array $joinedRelationsParents = [];
    protected array $joinsAddedByRelations = [];

    /**
     * $tableAlias used in conditions and joins. Default: $table::getAlias()
     */
    public static function from(TableInterface $table, ?string $tableAlias = null): static
    {
        return new static($table, $tableAlias);
    }

    /**
     * $tableAlias used in conditions and joins. Default: $table::getAlias()
     */
    public function __construct(TableInterface $table, ?string $tableAlias = null)
    {
        $this->table = $table;
        $this->tableStructure = $table->getTableStructure();
        $this->setTableAlias($tableAlias ?: $this->getTable()->getTableAlias());
    }

    protected function getListOfFrobiddenOptionsInConditionsAndOptionsArray(): array
    {
        return [];
    }

    protected function processContainsOptionFromConfigsArray(array $contains): void
    {
        $optionName = QueryBuilderUtils::QUERY_PART_CONTAINS;
        $this->processSubcontainsOptionFromContainConfigArray(
            $contains,
            $this->getTable(),
            $this->getTableAlias(),
            "\$conditionsAndOptions['$optionName']"
        );
    }

    protected function processSubcontainsOptionFromContainConfigArray(
        array $contains,
        TableInterface $table,
        string $tableAlias,
        string $argPathForException
    ): void {
        foreach ($contains as $relationName => $columnsToSelectForRelation) {
            if ($columnsToSelectForRelation instanceof OrmJoinConfigInterface) {
                $this->join($columnsToSelectForRelation);
                continue;
            }

            $subContains = [];
            if (is_int($relationName)) {
                $relationName = $columnsToSelectForRelation;
                $columnsToSelectForRelation = ['*'];
            } elseif (empty($columnsToSelectForRelation)) {
                $columnsToSelectForRelation = [];
            }
            // parse "RelationName as RelationAlias"
            $relationAlias = $relationName;
            if (preg_match('%^\s*(.*?)\s+as\s+(.*)\s*$%i', $relationName, $matches)) {
                [, $relationName, $relationAlias] = $matches[1];
            }
            $relationConfig = $table->getTableStructure()->getRelation($relationName);
            if ($relationConfig->getType() === RelationInterface::HAS_MANY) {
                throw new \InvalidArgumentException(
                    "$argPathForException[$relationName]: one-to-many joins are not allowed"
                );
            }

            $foreignTable = $relationConfig->getForeignTable();
            $joinType = $relationConfig->getJoinType();
            if (is_array($columnsToSelectForRelation)) {
                [
                    $columnsToSelectForRelation,
                    $options,
                ] = QueryBuilderUtils::separateColumnsAndSuboptionsForContainsOption(
                    $columnsToSelectForRelation
                );

                if (isset($options[QueryBuilderUtils::CONTAINS_SUBOPTION_JOIN_TYPE])) {
                    $joinType = $options[QueryBuilderUtils::CONTAINS_SUBOPTION_JOIN_TYPE];
                }
                if (!empty($options[QueryBuilderUtils::CONTAINS_SUBOPTION_SUBCONTAINS])) {
                    $subContains = $options[QueryBuilderUtils::CONTAINS_SUBOPTION_SUBCONTAINS];
                }
                if (isset($options[QueryBuilderUtils::CONTAINS_SUBOPTION_ADDITIONAL_JOIN_CONDITIONS])) {
                    $additionalJoinConditions = $options[QueryBuilderUtils::CONTAINS_SUBOPTION_ADDITIONAL_JOIN_CONDITIONS];
                }
            }

            $ormJoinConfig = $relationConfig
                ->toJoinConfig($tableAlias, $relationAlias, $joinType)
                ->setForeignColumnsToSelect($columnsToSelectForRelation);

            if (!empty($additionalJoinConditions)) {
                $ormJoinConfig->setAdditionalJoinConditions($additionalJoinConditions);
            }

            $this->join($ormJoinConfig);

            if (!empty($subContains)) {
                $this->processSubcontainsOptionFromContainConfigArray(
                    is_array($subContains) ? $subContains : [$subContains],
                    $foreignTable,
                    $relationAlias,
                    $argPathForException . "[$relationName]"
                );
            }
        }
    }

    public function getTableName(): string
    {
        return $this->getTableStructure()->getTableName();
    }

    public function getTableSchemaName(): ?string
    {
        return $this->getTableStructure()->getSchema();
    }

    public function getConnection(): DbAdapterInterface
    {
        return $this->getTable()->getConnection(false);
    }

    public function getTable(): TableInterface
    {
        return $this->table;
    }

    protected function getNewRecord(): RecordInterface
    {
        return $this->table->newRecord();
    }

    protected function getTableStructure(): TableStructureInterface
    {
        return $this->tableStructure;
    }

    public function fetchOneAsDbRecord(): RecordInterface
    {
        return $this->getNewRecord()->fromDbData($this->fetchOne());
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
        }

        return parent::getCountQuery($ignoreLeftJoins);
    }

    /* --------------------------------> SERVICE METHODS <-------------------------------> */

    protected function normalizeJoinDataForRecord(
        NormalJoinConfigInterface $joinConfig,
        array $data
    ): array
    {
        $data = parent::normalizeJoinDataForRecord($joinConfig, $data);
        if ($joinConfig instanceof OrmJoinConfigInterface) {
            $pkName = $joinConfig->getForeignTable()->getPkColumnName();
            if (array_key_exists($pkName, $data) && $data[$pkName] === null) {
                // not existing related record
                return [];
            }
        }
        return $data;
    }

    protected function beforeQueryBuilding(): void
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

    /**
     * @throws \UnexpectedValueException
     */
    protected function processRawColumns(): static
    {
        parent::processRawColumns();
        foreach ($this->columnsToSelectFromJoinedRelations as $joinName => $columns) {
            try {
                if (empty($columns)) {
                    $columns = [];
                } elseif (!is_array($columns)) {
                    $columns = [$columns];
                }
                $this->getJoin($joinName)->setForeignColumnsToSelect($columns);
            } catch (\InvalidArgumentException $exc) {
                throw new \UnexpectedValueException('SELECT: ' . $exc->getMessage(), 0);
            }
        }
        return $this;
    }

    /**
     * @throws \UnexpectedValueException
     */
    protected function normalizeWildcardColumn(
        ?string $joinName = null,
        ?array $excludeColumns = null,
        bool $includeHeavyColumns = false
    ): array {
        if ($joinName === null) {
            $tableStructure = $this->getTableStructure();
        } else {
            $join = $this->getJoin($joinName);
            if ($join instanceof OrmJoinConfigInterface) {
                $tableStructure = $join->getForeignTable()->getTableStructure();
            } else {
                // we have no access to TableStructureOld for this join
                return [
                    $this->analyzeColumnName('*', null, $joinName, 'SELECT')
                ];
            }
        }
        $normalizedColumns = [];
        if ($excludeColumns === null) {
            $excludeColumns = [];
        }
        $existingColumns = $tableStructure->getRealColumns();
        if (empty($existingColumns)) {
            throw new \UnexpectedValueException(
                __METHOD__ . '(): ' . get_class($tableStructure) . ' has no columns that exist in DB'
            );
        }
        foreach ($existingColumns as $columnName => $config) {
            if (($includeHeavyColumns || !$config->isHeavyValues()) && !in_array($columnName, $excludeColumns, true)) {
                $normalizedColumns[] = $this->analyzeColumnName($columnName, null, $joinName, 'SELECT');
            }
        }
        return $normalizedColumns;
    }

    protected function resolveColumnsToBeSelectedForJoin(
        string $joinName,
        string|array $columns,
        ?string $parentJoinName = null,
        bool $appendColumnsToExisting = false
    ): void {
        if (is_array($columns) && !empty($columns)) {
            $filteredColumns = [];
            foreach ($columns as $columnAlias => $columnName) {
                if ($columnAlias === '*' && empty($columnName)) {
                    // ['*' => []] situation - convert to select '*'
                    $columnName = $columnAlias;
                    $columnAlias = -1; //< pretend that it is an index in array
                }
                if ($columnAlias === '*') {
                    // all columns except those listed in $columnName
                    $joinColumns = $this->getOrmJoin($joinName)
                        ->getForeignTable()
                        ->getTableStructure()
                        ->getRealColumns();
                    $filteredColumns = array_merge(
                        $filteredColumns,
                        array_diff(
                            array_keys($joinColumns),
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
     */
    protected function getJoin(string $joinName): NormalJoinConfigInterface|CrossJoinConfigInterface
    {
        $joins = explode('.', $joinName);
        foreach ($joins as $subJoin) {
            if (
                !$this->hasJoin($subJoin, true)
                && (
                    array_key_exists($subJoin, $this->joinedRelationsParents) //< array_key_exists is correct
                    || $this->getTableStructure()->hasRelation($subJoin)
                )
            ) {
                $this->addJoinFromRelation($subJoin);
            }
        }
        return parent::getJoin($joins[count($joins) - 1]);
    }

    protected function getOrmJoin(string $joinName): OrmJoinConfigInterface
    {
        $join = $this->getJoin($joinName);
        $this->validateJoin($join);
        /** @var OrmJoinConfigInterface $join - validated by guardJoinClass */
        return $join;
    }

    /**
     * Create join from relation.
     * Relation name detected by $this->getRelationNameByJoinName($joinName).
     * @throws \UnexpectedValueException
     */
    protected function addJoinFromRelation(string $joinName): static
    {
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
                $relation = $this->getTableStructure()->getRelation($relationName);
                if ($relation->getType() === RelationInterface::HAS_MANY) {
                    throw new \UnexpectedValueException(
                        "Relation '{$relationName}' has type 'HAS MANY' and should not be used as JOIN (not optimal). "
                        . 'Select that records outside of OrmSelect.'
                    );
                }
                $joinConfig = $this->getTable()->getJoinConfigForRelation(
                    $relation,
                    $this->getTableAlias(),
                    $joinName
                );
            } else {
                // join on other join
                $this->addJoinFromRelation($parentJoinName);
                $parentJoin = $this->getOrmJoin($parentJoinName);
                $foreignTable = $parentJoin->getForeignTable();
                $relation = $foreignTable->getTableStructure()->getRelation($relationName);
                if ($relation->getType() === RelationInterface::HAS_MANY) {
                    throw new \UnexpectedValueException(
                        "Relation '{$relationName}' has type 'HAS MANY' and should not be used as JOIN (not optimal). "
                        . 'Select that records outside of OrmSelect.'
                    );
                }
                $joinConfig = $foreignTable->getJoinConfigForRelation(
                    $relation,
                    $parentJoin->getJoinName(),
                    $joinName
                );
            }
            $this->joinsAddedByRelations[] = $joinName;
            if (empty($this->columnsToSelectFromJoinedRelations[$joinName])) {
                // select no columns for this join
                $joinConfig->setForeignColumnsToSelect([]);
            } else {
                $joinConfig->setForeignColumnsToSelect(
                    is_array($this->columnsToSelectFromJoinedRelations[$joinName])
                        ? $this->columnsToSelectFromJoinedRelations[$joinName]
                        : [$this->columnsToSelectFromJoinedRelations[$joinName]]
                );
            }

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
        $assembled = QueryBuilderUtils::assembleWhereConditionsFromArray(
            $this->getConnection(),
            $conditions,
            function ($columnName) use ($joinName, $subject) {
                return $this->columnQuoterForConditions($columnName, $joinName, $subject);
            },
            'AND',
            function ($columnName, $rawValue) use ($subject, $joinName) {
                return $this->processConditionValue($subject, $columnName, $rawValue, $joinName);
            }
        );
        $assembled = trim($assembled);
        return empty($assembled) ? '' : " {$subject} {$assembled}";
    }

    protected function processConditionValue(
        string $subject,
        ?string $columnName,
        mixed $rawValue,
        ?string $joinName = null
    ): mixed {
        if ($rawValue instanceof DbExpr) {
            return $this->quoteDbExpr($rawValue);
        }

        if ($rawValue instanceof SelectQueryBuilderInterface) {
            return $rawValue;
        }

        $columnInfo = $this->analyzeColumnName($columnName, null, $joinName, $subject);
        // Run validation for condition value except when there are:
        // 1. json selector - there may be any type of value and validation by $column is not possible
        // 2. type casting - it is responsibility of developer so ORM should not interfere
        if ($columnInfo['json_selector'] || $columnInfo['type_cast']) {
            return $rawValue;
        }
        if ($columnInfo['join_name'] === null) {
            $column = $this->getTableStructure()->getColumn($columnInfo['name']);
        } else {
            $join = $this->getJoin($columnInfo['join_name']);
            if ($join instanceof OrmJoinConfigInterface) {
                $column = $join->getForeignTable()
                    ->getTableStructure()
                    ->getColumn($columnInfo['name']);
            } else {
                // join has no link to Table, so we cannot get $column and validate value
                return $rawValue;
            }
        }
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
        if (!empty($errors)) {
            throw new \UnexpectedValueException(
                "Invalid {$subject} condition value provided for column [{$columnName}]. Value: "
                . var_export($rawValue, true) . '; Errors: ' . implode('; ', $errors) . '.'
            );
        }
        return $rawValue;
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
    protected function validateColumnInfo(array $columnInfo, string $subject): void
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
        } elseif ($columnInfo['join_name'] === null) {
            $isValid = $this->getTableStructure()->hasColumn($columnInfo['name']);
            if (!$isValid) {
                throw new \UnexpectedValueException(
                    "{$subject}: Column with name [{$columnInfo['name']}] not found in "
                    . get_class($this->getTableStructure())
                );
            }
        } else {
            $join = $this->getJoin($columnInfo['join_name']);
            if ($join instanceof OrmJoinConfigInterface) {
                $foreignTableStructure = $join->getForeignTable()->getTableStructure();
                $isValid = (
                    $columnInfo['name'] === '*'
                    || $foreignTableStructure->hasColumn($columnInfo['name'])
                );
                if (!$isValid) {
                    throw new \UnexpectedValueException(
                        "{$subject}: Column with name [{$columnInfo['join_name']}.{$columnInfo['name']}] not found in "
                        . get_class($foreignTableStructure)
                    );
                }
            }
        }
    }

    protected function validateColumnInfoForCondition(array $columnInfo, string $subject): void
    {
        $this->validateColumnInfo($columnInfo, $subject);
    }
}
