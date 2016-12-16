<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbAdapterInterface;
use Swayok\Utils\StringUtils;

abstract class FakeTable extends Table {

    protected $tableName;
    /** @var FakeTableStructure */
    protected $tableStructure;
    protected $recordClass;
    /** @var DbAdapterInterface */
    protected $connection;

    static private $fakesCreated = 0;

    /**
     * @param string $tableName
     * @param array $columns - key-value array where key is column name and value is column type or
     *      key is int and value is column name
     * @param DbAdapterInterface $connection
     * @return FakeTable
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function makeNewFakeTable($tableName, array $columns = [], DbAdapterInterface $connection = null) {
        if (!is_string($tableName) || trim($tableName) === '' || !DbAdapter::isValidDbEntityName($tableName)) {
            throw new \InvalidArgumentException(
                '$tableName argument bust be a not empty string that matches DB entity naming rules (usually alphanumeric with underscores)'
            );
        }
        static::$fakesCreated++;
        $namespace = 'PeskyORM\ORM\Fakes';
        $className = 'FakeTable' . static::$fakesCreated . 'For' . StringUtils::classify($tableName);
        $class = <<<VIEW
namespace {$namespace};

use PeskyORM\ORM\FakeTable;

class {$className} extends FakeTable {

}
VIEW;
        eval($class);
        /** @var FakeTable $fullClassName */
        $fullClassName = $namespace . '\\' . $className;
        $table = $fullClassName::getInstance();
        $table->tableName = $tableName;
        if (!empty($columns)) {
            $table->getTableStructure()->setTableColumns($columns);
        }
        if ($connection) {
            $table->setConnection($connection);
        }
        return $table;
    }

    /**
     * @return DbAdapterInterface
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function getConnection() {
        return static::getInstance()->connection ?: parent::getConnection();
    }

    /**
     * @param DbAdapterInterface $connection
     * @return $this
     */
    public function setConnection(DbAdapterInterface $connection) {
        $this->connection = $connection;
        return $this;
    }

    /**
     * Table schema description
     * @return FakeTableStructure
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getTableStructure() {
        if (!$this->tableStructure) {
            $this->tableStructure = FakeTableStructure::makeNewFakeStructure($this->tableName);
        }
        return $this->tableStructure;
    }

    /**
     * @return FakeRecord
     * @throws \BadMethodCallException
     */
    public function newRecord() {
        if (!$this->recordClass) {
            $this->recordClass = FakeRecord::makeNewFakeRecord($this);
        }
        return new $this->recordClass;
    }
}