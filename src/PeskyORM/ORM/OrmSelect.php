<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;
use PeskyORM\Core\DbSelect;
use PeskyORM\Core\Utils;

class OrmSelect extends DbSelect {

    /**
     * @var DbTableInterface
     */
    protected $table;
    /**
     * @var DbTableStructure
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
     * @param DbTableInterface $table
     * @return static
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function from(DbTableInterface $table) {
        return new static($table);
    }

    /**
     * @param DbTableInterface $table - table name or DbTable object
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     */
    public function __construct(DbTableInterface $table) {
        $this->setTable($table);
        parent::__construct($table::getName(), $table::getConnection());
    }

    /*protected function parseNormalizedConfigsArray(array $conditionsAndOptions) {
        if (!empty($conditionsAndOptions['CONTAINS'])) {
            foreach ($conditionsAndOptions['CONTAINS'] as $relation) {
                $this->addRelation($relation);
            }
        }
        unset($conditionsAndOptions['CONTAINS']);
        parent::parseNormalizedConfigsArray($conditionsAndOptions);
    }*/

    /*protected function normalizeConditionsAndOptionsArray(array $conditionsAndOptions) {
        $conditionsAndOptions = parent::normalizeConditionsAndOptionsArray($conditionsAndOptions);
        if (array_key_exists('CONTAIN', $conditionsAndOptions)) {
            $conditionsAndOptions['CONTAINS'] = $conditionsAndOptions['CONTAIN'];
            unset($conditionsAndOptions['CONTAIN']);
        }
        if (array_key_exists('CONTAINS', $conditionsAndOptions) && !is_array($conditionsAndOptions['CONTAINS'])) {
            if (is_string($conditionsAndOptions['CONTAINS'])) {
                $conditionsAndOptions['CONTAINS'] = [$conditionsAndOptions['CONTAINS']];
            } else {
                throw new \InvalidArgumentException(
                    'Key "CONTAINS" in $conditionsAndOptions argument must be an array or a string'
                );
            }
        }
        return $conditionsAndOptions;
    }*/

    /**
     * @return DbRecord
     * @throws \PeskyORM\ORM\Exception\InvalidDataException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     * @throws \PDOException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function fetchOneAsDbRecord() {
        return $this->table->newRecord()->fromDbData($this->fetchOne());
    }

    /**
     * @return DbTableInterface
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * @return DbTableStructure
     */
    public function getTableStructure() {
        return $this->tableStructure;
    }
    
    /**
     * Build valid 'JOIN' settings from 'CONTAIN' table aliases
     * @param array $conditionsAndOptions
     * @return mixed $where
     */
    /*public function resolveContains(array $conditionsAndOptions) {
        if (is_array($conditionsAndOptions)) {
            if (!empty($conditionsAndOptions['CONTAIN'])) {
                if (!is_array($conditionsAndOptions['CONTAIN'])) {
                    $conditionsAndOptions['CONTAIN'] = [$conditionsAndOptions['CONTAIN']];
                }
                $conditionsAndOptions['JOIN'] = [];

                foreach ($conditionsAndOptions['CONTAIN'] as $alias => $columns) {
                    if (is_int($alias)) {
                        $alias = $columns;
                        $columns = !empty($relation['fields']) ? $relation['fields'] : '*';
                    }
                    $relationConfig = $this->getTableRealtaion($alias);
                    if ($relationConfig->getType() === DbTableRelation::HAS_MANY) {
                        throw new DbModelException($this, "Queries with one-to-many joins are not allowed via 'CONTAIN' key");
                    } else {
                        $model = $this->getRelatedModel($alias);
                        $additionalConditions = $relationConfig->getAdditionalJoinConditions();
                        $joinType = $relationConfig->getJoinType();
                        if (is_array($columns)) {
                            if (isset($columns['TYPE'])) {
                                $joinType = $columns['TYPE'];
                            }
                            unset($columns['TYPE']);
                            if (isset($columns['CONDITIONS'])) {
                                $additionalConditions = array_replace_recursive($additionalConditions, $columns['CONDITIONS']);
                            }
                            unset($columns['CONDITIONS']);
                            if (!empty($columns['CONTAIN'])) {
                                $subContains = ['CONTAIN' => $columns['CONTAIN']];
                            }
                            unset($columns['CONTAIN']);
                            if (empty($columns)) {
                                $columns = '*';
                            }
                        }

                        $conditionsAndOptions['JOIN'][$alias] = DbJoinConfig::create($alias)
                            ->setConfigForLocalTable($this, $relationConfig->getColumn())
                            ->setJoinType($joinType)
                            ->setConfigForForeignTable($model, $relationConfig->getForeignColumn())
                            ->setAdditionalJoinConditions($additionalConditions)
                            ->setForeignColumnsToSelect($columns)
                            ->getConfigsForDbQuery();

                        if (!empty($subContains)) {
                            $subJoins = $model->resolveContains($subContains);
                            $conditionsAndOptions['JOIN'] = array_merge($conditionsAndOptions['JOIN'], $subJoins['JOIN']);
                        }
                    }
                }
                if (empty($conditionsAndOptions['JOIN'])) {
                    unset($conditionsAndOptions['JOIN']);
                }
            }
            unset($conditionsAndOptions['CONTAIN']);
        }
        return $conditionsAndOptions;
    }*/

    /**
     * @param string $relationName
     * @param null|array $columns
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    /*public function contain($relationName, $columns = null) {
        if ($columns !== null && !is_array($columns)) {
            throw new \InvalidArgumentException('$columns argument must be an array or null');
        }
        $columns = ($columns === null) ? [] : $this->normalizeColumnsList($columns);
        $this->contains[$relationName] = compact('columns', 'conditions');
        // todo: covert DbTableRelation to DbJoinConfig and add it as join
    }*/

    /**
     * @param array $columns
     * @param null $joinName
     * @return array
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    /*protected function normalizeColumnsList(array $columns, $joinName = null) {
        foreach ($columns as $columnAlias => $columnName) {
            if (
                !is_numeric($columnAlias)
                && !$this->hasJoin($columnAlias)
            ) {
                if ($joinName === null && $this->getTable()->hasRelation($columnAlias)) {
                    $this->contain($columnAlias, null, []);
                } else if (($foreignTable = $this->getJoin($joinName)->getForeignDbTable())->hasRelation($columnAlias)) {
                    $this->contain($columnAlias, null, [], $foreignTable);
                }
            }
        }
        return parent::normalizeColumnsList($columns, $joinName);
    }*/

    /**
     * @param OrmJoinConfig $joinConfig
     * @param bool $append
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function join(OrmJoinConfig $joinConfig, $append = true) {
        parent::join($joinConfig, $append);
        return $this;
    }

    /* ------------------------------------> SERVICE METHODS <-----------------------------------> */

    /**
     * @param DbTableInterface $table
     * @throws \BadMethodCallException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    protected function setTable(DbTableInterface $table) {
        $this->table = $table;
        $this->tableStructure = $table::getStructure();
    }

    protected function beforeQueryBuilding() {
        if ($this->isDirty('joins')) {
            $this->setDirty('columns');
            $this->setDirty('where');
            $this->setDirty('having');
        }
        if ($this->isDirty('columns') || $this->isDirty('where') || $this->isDirty('having')) {
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

    protected function normalizeWildcardColumn($joinName = null) {
        if ($joinName === null) {
            $normalizedColumns = [];
            foreach ($this->getTableStructure()->getColumns() as $columnName => $config) {
                if ($config->isItExistsInDb()) {
                    $normalizedColumns[] = $this->analyzeColumnName($columnName);
                }
            }
            return $normalizedColumns;
        }
        return parent::normalizeWildcardColumn($joinName);
    }

    protected function resolveColumnsToBeSelectedForJoin($joinName, $columns, $parentJoinName = null, $appendColumnsToExisting = false) {
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
        if ($appendColumnsToExisting && array_key_exists($joinName, $this->columnsToSelectFromJoinedRelations)) {
            $this->columnsToSelectFromJoinedRelations[$joinName] = array_merge(
                $this->columnsToSelectFromJoinedRelations[$joinName],
                $filteredColumns
            );
        } else {
            $this->columnsToSelectFromJoinedRelations[$joinName] = $filteredColumns;
        }
        $this->joinedRelationsParents[$joinName] = $parentJoinName;
    }

    protected function collectJoinedColumnsForQuery() {
        foreach ($this->columnsToSelectFromJoinedRelations as $joinName => $columns) {
            $this->getJoin($joinName)->setForeignColumnsToSelect(empty($columns) ? [] : $columns);
        }
        return parent::collectJoinedColumnsForQuery();
    }

    /**
     * @param string $joinName
     * @return OrmJoinConfig
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function getJoin($joinName) {
        if (
            !$this->hasJoin($joinName, true)
            && (
                array_key_exists($joinName, $this->joinedRelationsParents)
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
     * @throws \BadMethodCallException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     */
    protected function addJoinFromRelation($joinName) {
        if (!$this->hasJoin($joinName, false)) {
            if (
                !array_key_exists($joinName, $this->joinedRelationsParents)
                && $this->getTableStructure()->hasRelation($joinName)
            ) {
                // this may happen only when relation is used in WHERE or HAVING
                $this->joinedRelationsParents[$joinName] = null;
                $this->joinNameToRelationName[$joinName] = $joinName;
                $this->columnsToSelectFromJoinedRelations[$joinName] = [];
            }
            $relationName = $this->getRelationByJoinName($joinName);
            $parentJoinName = $this->joinedRelationsParents[$joinName];
            if ($parentJoinName === null) {
                // join on base table
                if ($this->getTableStructure()->getRelation($relationName)->getType() === DbTableRelation::HAS_MANY) {
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
                if ($foreignTable->getTableStructure()->getRelation($relationName)->getType() === DbTableRelation::HAS_MANY) {
                    throw new \UnexpectedValueException(
                        "Relation '{$relationName}' has type 'HAS MANY' and should not be used as JOIN (not optimal). "
                            . 'Select that records outside of OrmSelect.'
                    );
                }
                $joinConfig = $foreignTable->getJoinConfigForRelation($relationName, $parentJoin->getJoinName(), $joinName);
            }
            $this->joinsAddedByRelations[] = $joinName;
            $this->join($joinConfig);
        }
        return $this;
    }

    /**
     * @param array $conditions
     * @param string $subject
     * @param null $joinName - string: used when assembling conditions for join
     * @return string
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    protected function makeConditions(array $conditions, $subject = 'WHERE', $joinName = null) {
        $assembled = Utils::assembleWhereConditionsFromArray(
            $this->getConnection(),
            $conditions,
            function ($columnName) use ($subject, $joinName) {
                $columnInfo = $this->analyzeColumnName($columnName, $joinName);
                return $this->makeColumnNameForCondition($columnInfo, $subject);
            },
            'AND',
            function ($columnName, $value) use ($subject, $joinName) {
                if ($value instanceof DbExpr) {
                    return $this->quoteDbExpr($value);
                } else if (!($columnName instanceof DbExpr)) {
                    $columnInfo = $this->analyzeColumnName($columnName, $joinName);
                    if ($columnInfo['join_name'] === null) {
                        $errors = $this->getTableStructure()->getColumn($columnInfo['name'])->validateValue($value);
                    } else {
                        $errors = $this
                            ->getJoin($columnInfo['join_name'])
                            ->getForeignDbTable()
                            ->getStructure()
                            ->getColumn($columnInfo['name'])
                            ->validateValue($value);
                    }
                    if (!empty($errors)) {
                        throw new \UnexpectedValueException(
                            "Invalid {$subject} condition value provided for column [{$columnName}]. Value: " . print_r($value, true)
                        );
                    }
                }
                return $value;
            }
        );
        $assembled = trim($assembled);
        return empty($assembled) ? '' : " {$subject} {$assembled}";
    }

    protected function makeColumnNameWithAliasForQuery(array $columnInfo) {
        $this->validateColumnInfo($columnInfo, 'SELECT');
        return parent::makeColumnNameWithAliasForQuery($columnInfo);
    }

    protected function makeColumnNameForCondition(array $columnInfo, $subject = 'WHERE') {
        $this->validateColumnInfo($columnInfo, $subject);
        return parent::makeColumnNameForCondition($columnInfo);
    }

    protected function getRelationByJoinName($joinName) {
        if (!array_key_exists($joinName, $this->joinNameToRelationName)) {
            throw new \InvalidArgumentException("There is no known relation for join named '{$joinName}'");
        }
        return $this->joinNameToRelationName[$joinName];
    }

    /**
     * @param array $columnInfo
     * @param string $subject - used in exceptions, can be 'SELECT', 'WHERE' or 'HAVING'
     * @throws \UnexpectedValueException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    protected function validateColumnInfo(array $columnInfo, $subject) {
        if ($columnInfo['name'] instanceof DbExpr) {
            if (
                $columnInfo['name']->isValidationAllowed()
                && preg_match_all('%`(.+)`\.`(.+)`(?!\.)%', $columnInfo['name']->get(), $matches)
            ) {
                foreach ($matches[1] as $index => $joinName) {
                    $this->validateColumnInfo(
                        $this->analyzeColumnName($matches[2][$index], null, $joinName),
                        $subject
                    );
                }
            }
        } else {
            if ($columnInfo['join_name'] === null) {
                $isValid = $this->getTableStructure()->hasColumn($columnInfo['name']);
                if (!$isValid) {
                    throw new \UnexpectedValueException(
                        "{$subject}: column with name [{$columnInfo['name']}] not found in "
                            . get_class($this->getTableStructure())
                    );
                }
            } else {
                $foreignTableStructure = $this
                    ->getJoin($columnInfo['join_name'])
                    ->getForeignDbTable()
                    ->getTableStructure();
                $isValid = $foreignTableStructure->hasColumn($columnInfo['name']);
                if (!$isValid) {
                    throw new \UnexpectedValueException(
                        "{$subject}: column with name [{$columnInfo['join_name']}.{$columnInfo['name']}] not found in "
                            . get_class($foreignTableStructure)
                    );
                }
            }
        }
    }

    /*protected function addRelation(DbTableRelation $relation) {
        if (!array_key_exists($relation->getName(), $this->relations)) {
            throw new \InvalidArgumentException("Relation with name '{$relation->getName()}' already defined");
        }
        $this->relations[$relation->getName()] = $relation;
    }*/

}