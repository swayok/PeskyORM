<?php

namespace PeskyORM;

use PeskyORM\ORM\Relation;

class DbRelationConfig extends Relation {

    /**
     * @deprecated
     * @return string
     */
    public function getColumn() {
        return $this->getLocalColumnName();
    }

    /**
     * @deprecated
     * @return string
     */
    public function getForeignColumn() {
        return $this->getForeignColumnName();
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