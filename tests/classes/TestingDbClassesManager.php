<?php

namespace PeskyORMTest;

use PeskyORM\ORM\DbClassesManager;
use PeskyORM\ORM\DbRecord;
use PeskyORM\ORM\DbTable;
use PeskyORM\ORM\DbTableStructure;
use Swayok\Utils\StringUtils;

class TestingDbClassesManager extends DbClassesManager {

    protected function makeFullClassName($tableNameOrAlias, $suffix) {
        $baseClassName = StringUtils::classify($tableNameOrAlias);
        return __NAMESPACE__ . '\\' . $baseClassName . '\\' . $baseClassName . $suffix;
    }

    /**
     * @param string $tableName
     * @return DbTable
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getTableInstance($tableName) {
        /** @var DbTable $className */
        $className = $this->makeFullClassName($tableName, 'Table');
        return $className::getInstance();
    }

    /**
     * @param string $tableAlias
     * @return DbTable
     * @throws \BadMethodCallException
     * @throws \PeskyORM\ORM\Exception\OrmException
     * @throws \InvalidArgumentException
     */
    public function getTableInstanceByAlias($tableAlias) {
        return $this->getTableInstance($tableAlias);
    }

    /**
     * @param string $tableName
     * @return DbTableStructure
     */
    public function getTableStructure($tableName) {
        /** @var DbTableStructure $className */
        $className = $this->makeFullClassName($tableName, 'TableStructure');
        return $className::getInstance();
    }

    /**
     * @param string $tableName
     * @return DbRecord
     */
    public function newRecord($tableName) {
        /** @var DbRecord $className */
        $className = $this->makeFullClassName($tableName, '');
        return $className::newEmptyRecord();
    }
}