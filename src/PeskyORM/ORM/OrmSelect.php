<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbSelect;
use SebastianBergmann\ObjectEnumerator\InvalidArgumentException;

class OrmSelect extends DbSelect {

    /**
     * @var DbTable
     */
    protected $table;
    /**
     * @var DbTableStructure
     */
    protected $tableStructure;
    /**
     * @var array
     */
    protected $relations = [];
    /**
     * @var array
     */
    protected $contains = [];

    /**
     * @param DbTable $table
     * @return $this
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function from(DbTable $table) {
        return new static($table);
    }

    /**
     * @param DbTable $table - table name or DbTable object
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \UnexpectedValueException
     */
    public function __construct(DbTable $table) {
        $this->setTable($table);
        parent::__construct($table::getName(), $table::getConnection());
    }

    protected function parseNormalizedConfigsArray(array $conditionsAndOptions) {
        if (!empty($conditionsAndOptions['CONTAINS'])) {
            foreach ($conditionsAndOptions['CONTAINS'] as $relation) {
                $this->addRelation($relation);
            }
        }
        unset($conditionsAndOptions['CONTAINS']);
        parent::parseNormalizedConfigsArray($conditionsAndOptions);
    }

    protected function normalizeConditionsAndOptionsArray(array $conditionsAndOptions) {
        $conditionsAndOptions = parent::normalizeConditionsAndOptionsArray($conditionsAndOptions);
        if (array_key_exists('CONTAIN', $conditionsAndOptions)) {
            $conditionsAndOptions['CONTAINS'] = $conditionsAndOptions['CONTAIN'];
            unset($conditionsAndOptions['CONTAIN']);
        }
        if (array_key_exists('CONTAINS', $conditionsAndOptions) && !is_array($conditionsAndOptions['CONTAINS'])) {
            if (is_string($conditionsAndOptions['CONTAINS'])) {
                $conditionsAndOptions['CONTAINS'] = [$conditionsAndOptions['CONTAINS']];
            } else {
                throw new InvalidArgumentException(
                    'Key "CONTAINS" in $conditionsAndOptions argument must be an array or a string'
                );
            }
        }
        return $conditionsAndOptions;
    }

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
     * @return DbTable
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
    public function resolveContains(array $conditionsAndOptions) {
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
    }

    /**
     * @param string $relationName
     * @param null|array $columns
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    public function contain($relationName, $columns = null, array $conditions = []) {
        if ($columns !== null && !is_array($columns)) {
            throw new \InvalidArgumentException('$columns argument must be an array or null');
        }
        $columns = ($columns === null) ? [] : $this->normalizeColumnsList($columns);
        $this->contains[$relationName] = compact('columns', 'conditions');
    }

    /* ------------------------------------> SERVICE METHODS <-----------------------------------> */

    /**
     * @param DbTable $table
     * @throws \BadMethodCallException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     */
    protected function setTable(DbTable $table) {
        $this->tableName = $table->getName();
        $this->table = $table;
        $this->tableStructure = $table->getStructure();
    }

    protected function addRelation(DbTableRelation $relation) {
        if (!array_key_exists($relation->getName(), $this->relations)) {
            throw new \InvalidArgumentException("Relation with name '{$relation->getName()}' already defined");
        }
        $this->relations[$relation->getName()] = $relation;
    }

}