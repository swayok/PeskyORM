<?php

namespace PeskyORM\ORM;

use Exceptions\Data\ValidationException;
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
    protected $columnsForRelations = [];
    /**
     * @var array
     */
    protected $relationsParents = [];

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
        parent::beforeQueryBuilding();
        foreach ($this->relationsParents as $relationName => $parentRelation) {
            $this->loadRelation($parentRelation);
            $this->loadRelation($relationName);
        }
        $this->relationsParents = []; //< needed only once
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

    protected function resolveColumnsToBeSelectedForJoin($joinName, $columns, $parentJoinName = null, $appendToExisting = false) {
        $this->columnsForRelations[$joinName] = empty($columns) ? [] : $this->normalizeColumnsList($columns, $joinName);
        $this->relationsParents[$joinName] = $parentJoinName;
    }

    protected function collectJoinedColumnsForQuery() {
        foreach ($this->columnsForRelations as $relationName => $columns) {
            $this->getJoin($relationName)->setForeignColumnsToSelect(empty($columns) ? [] : $columns);
        }
        return parent::collectJoinedColumnsForQuery();
    }

    /**
     * Load relation and all its parents
     * @param string $relationName
     * @return $this
     * @throws \InvalidArgumentException
     */
    protected function loadRelation($relationName) {
        if (!$this->hasJoin($relationName)) {
            if ($this->relationsParents[$relationName] === null) {
                $this->join($this->getTable()->getJoinConfigForRelation($relationName, $this->getTableAlias()));
            } else {
                $parentJoin = $this
                    ->loadRelation($this->relationsParents[$relationName])
                    ->getJoin($this->relationsParents[$relationName]);
                $this->join(
                    $parentJoin->getForeignDbTable()->getJoinConfigForRelation($relationName, $parentJoin->getJoinName())
                );
            }
        }
        return $this;
    }

    /**
     * @param array $conditions
     * @param string $subject
     * @return string
     * @throws \PDOException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     * @throws \Exceptions\Data\ValidationException
     */
    protected function makeConditions(array $conditions, $subject = 'WHERE') {
        $assembled = Utils::assembleWhereConditionsFromArray(
            $this->getConnection(),
            $conditions,
            function ($columnName) {
                $columnInfo = $this->analyzeColumnName($columnName);
                return $this->makeColumnNameForCondition($columnInfo);
            },
            'AND',
            function ($columnName, $value) {
                $columnInfo = $this->analyzeColumnName($columnName);
                if (!($columnInfo['name'] instanceof DbExpr)) {
                    if ($columnInfo['join_name'] === null) {
                        $errors = $this->getTableStructure()->getColumn($columnInfo['name'])->validateValue($value);
                    } else {
                        $errors = $this
                            ->loadRelation($columnInfo['join_name'])
                            ->getJoin($columnInfo['join_name'])
                            ->getForeignDbTable()
                            ->getStructure()
                            ->getColumn($columnInfo['name'])
                            ->validateValue($value);
                    }
                    if (!empty($errors)) {
                        throw new ValidationException(
                            "Invalid condition value [{$value}] provided for column '{$columnName}'"
                        );
                    }
                }
            }
        );
        $assembled = trim($assembled);
        return empty($assembled) ? '' : " {$subject} {$assembled}";
    }

    protected function makeColumnNameWithAliasForQuery(array $columnInfo) {
        $this->validateColumnInfo($columnInfo);
        return parent::makeColumnNameWithAliasForQuery($columnInfo);
    }

    protected function makeColumnNameForCondition(array $columnInfo) {
        $this->validateColumnInfo($columnInfo);
        return parent::makeColumnNameForCondition($columnInfo);
    }

    protected function validateColumnInfo(array $columnInfo) {
        if (!($columnInfo['name'] instanceof DbExpr)) {
            if ($columnInfo['join_name'] === null) {
                $isValid = $this->getTableStructure()->hasColumn($columnInfo['name']);
                if (!$isValid) {
                    throw new ValidationException("Invalid column name [{$columnInfo['name']}]");
                }
            } else {
                $isValid = $this
                    ->loadRelation($columnInfo['join_name'])
                    ->getJoin($columnInfo['join_name'])
                    ->getForeignDbTable()
                    ->getTableStructure()
                    ->hasColumn($columnInfo['name']);
                if (!$isValid) {
                    throw new ValidationException(
                        "Invalid column name [{$columnInfo['join_name']}.{$columnInfo['name']}]"
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