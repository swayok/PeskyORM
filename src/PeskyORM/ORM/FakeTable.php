<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbAdapterInterface;
use Swayok\Utils\StringUtils;

abstract class FakeTable extends Table {

    protected $tableName;
    /** @var FakeTableStructure */
    protected $tableStructure;
    /** @var TableStructureInterface */
    protected $tableStructureToCopy;
    protected $recordClass;
    /** @var DbAdapterInterface */
    protected $connection;

    static private $fakesCreated = 0;

    /**
     * @param TableInterface $tableToMimic
     * @param string $fakeTableName
     * @return FakeTable
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    static public function mimicTable(TableInterface $tableToMimic, $fakeTableName) {
        $reflection = new \ReflectionClass($tableToMimic);
        return static::makeNewFakeTable(
                $fakeTableName,
                $tableToMimic->getTableStructure(),
                null,
                $reflection->getInterfaceNames(),
                $reflection->getTraitNames()
            )
            ->setRecordClass($tableToMimic->newRecord());
    }

    /**
     * @param string $tableName
     * @param array|TableStructureInterface $columnsOrTableStructure
     *      - array: key-value array where key is column name and value is column type or key is int and value is column name
     *      - TableStructureInterface: table structure to use instead of FakeTableStructure
     * @param DbAdapterInterface $connection
     * @param array $interfaces - full class names of interfaces that fake table must implement
     * @param array $traits - full names of traits that fake table must use
     * @return FakeTable
     * @throws \UnexpectedValueException
     * @throws \PeskyORM\Exception\OrmException
     * @throws \BadMethodCallException
     * @throws \InvalidArgumentException
     */
    static public function makeNewFakeTable(
        $tableName,
        $columnsOrTableStructure = null,
        DbAdapterInterface $connection = null,
        array $interfaces = [],
        array $traits = [],
        $classBody = ''
    ) {
        if (!is_string($tableName) || trim($tableName) === '' || !DbAdapter::isValidDbEntityName($tableName)) {
            throw new \InvalidArgumentException(
                '$tableName argument bust be a not empty string that matches DB entity naming rules (usually alphanumeric with underscores)'
            );
        }
        static::$fakesCreated++;
        $namespace = 'PeskyORM\ORM\Fakes';
        $className = 'FakeTable' . static::$fakesCreated . 'For' . StringUtils::classify($tableName);
        $implemets = !empty($interfaces) ? ' implements \\' . implode(', \\', $interfaces) : '';
        $use = !empty($traits) ? '    use \\' . implode(', \\', $traits) . ';' : '';
        $class = <<<VIEW
namespace {$namespace};

use PeskyORM\ORM\FakeTable;

class {$className} extends FakeTable{$implemets} {
{$use}
{$classBody}
}
VIEW;
        eval($class);
        /** @var FakeTable $fullClassName */
        $fullClassName = $namespace . '\\' . $className;
        $table = $fullClassName::getInstance();
        $table->tableName = $tableName;
        if (is_array($columnsOrTableStructure)) {
            $table->getTableStructure()->setTableColumns($columnsOrTableStructure);
        } else if ($columnsOrTableStructure instanceof TableStructureInterface) {
            $table->setTableStructureToCopy($columnsOrTableStructure);
        } else if ($columnsOrTableStructure !== null) {
            throw new \InvalidArgumentException(
                '$columnsOrTableStructure argument must be an array, instance of DbAdapterInterface or null. '
                    . gettype($columnsOrTableStructure) . ' received'
            );
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
     * @return TableStructureInterface|FakeTableStructure
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public function getTableStructure() {
        if (!$this->tableStructure) {
            $this->tableStructure = FakeTableStructure::makeNewFakeStructure($this->tableName, $this->tableStructureToCopy);
        }
        return $this->tableStructure;
    }

    /**
     * @param TableStructureInterface $tableStructure
     * @return $this
     */
    public function setTableStructureToCopy(TableStructureInterface $tableStructure) {
        $this->tableStructureToCopy = $tableStructure;
        return $this;
    }

    /**
     * @return FakeRecord
     * @throws \BadMethodCallException
     */
    public function newRecord() {
        if (!$this->recordClass) {
            $this->recordClass = FakeRecord::makeNewFakeRecordClass($this);
        }
        return new $this->recordClass;
    }

    /**
     * @param string $class
     * @return $this
     * @throws \InvalidArgumentException
     */
    public function setRecordClass($class) {
        if ($class instanceof RecordInterface) {
            $this->recordClass = get_class($class);
        } else if (!class_exists($class) || !in_array(RecordInterface::class, class_implements($class), true)) {
            throw new \InvalidArgumentException('$class argument is not a class or it does not implement RecordInterface');
        } else {
            $this->recordClass = $class;
        }
        return $this;
    }
}