<?php

namespace PeskyORM;

use PeskyORM\ORM\Record;
use PeskyORM\ORM\Relation;
use PeskyORM\ORM\TableInterface;

class DbRelationConfig extends Relation {

    /** @var string */
    protected $table;
    /** @var string */
    protected $column;

    /** @var string */
    protected $foreignColumn;

    /** @var string */
    protected $displayField;

    /** @var array */
    protected $customData = array();

    /**
     * @deprecated
     * @return string
     */
    public function getTable() {
        return $this->table;
    }

    /**
     * @deprecated
     * @param string $table
     * @return $this
     */
    public function setTable($table) {
        $this->table = $table;
        return $this;
    }

    /**
     * @deprecated
     * @return string
     */
    public function getColumn() {
        return $this->getLocalColumnName();
    }

    /**
     * @deprecated
     * @param string $column
     * @return $this
     */
    public function setColumn($column) {
        $this->setLocalColumnName($column);
        return $this;
    }

    /**
     * @deprecated
     * @return string
     */
    public function getForeignColumn() {
        return $this->getForeignColumnName();
    }

    /**
     * @return array
     * @throws \BadFunctionCallException
     */
    public function getAdditionalJoinConditions(TableInterface $localTable, ?string $localTableAlias, bool $forStandaloneSelect, ?Record $localRecord = null): array {
        if ($this->additionalJoinConditions instanceof \Closure) {
            $conditions = call_user_func(
                $this->additionalJoinConditions,
                $forStandaloneSelect,
                $dbObject
            );
            if (!is_array($conditions)) {
                throw new \BadFunctionCallException('additionalJoinConditions Closure must return array');
            }
            return $conditions;
        } else {
            return $this->additionalJoinConditions;
        }
    }

    /**
     * @deprecated
     * @param array|\Closure $conditionsForJoining - \Closure =
     *      function (bool $forStandaloneSelect, ?DbObject $dbObject = null) { return []; }
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setAdditionalConditions($conditionsForJoining) {
        return $this->setAdditionalJoinConditions($conditionsForJoining);
    }

    /**
     * @deprecated
     * @return string
     */
    public function getDisplayField() {
        return $this->getDisplayColumnName();
    }

    /**
     * @deprecated
     * @param string $displayField
     * @return $this
     */
    public function setDisplayField($displayField) {
        return $this->setDisplayColumnName($displayField);
    }

}