<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbAdapterInterface;
use Swayok\Utils\StringUtils;

abstract class FakeTable extends Table
{
    
    protected string $tableName;
    protected ?FakeTableStructure $tableStructure = null;
    protected ?TableStructureInterface $tableStructureToCopy = null;
    protected ?string $recordClass = null;
    protected ?DbAdapterInterface $connection = null;
    
    private static int $fakesCreated = 0;
    
    public static function mimicTable(TableInterface $tableToMimic, string $fakeTableName): FakeTable
    {
        $reflection = new \ReflectionClass($tableToMimic);
        return static::makeNewFakeTable(
            $fakeTableName,
            $tableToMimic->getTableStructure(),
            null,
            $reflection->getInterfaceNames(),
            $reflection->getTraitNames()
        )
            ->setRecordClass(get_class($tableToMimic->newRecord()));
    }
    
    /**
     * @param string $tableName
     * @param array|TableStructureInterface $columnsOrTableStructure
     *      - array: key-value array where key is column name and value is column type or key is int and value is column name
     *      - TableStructureInterface: table structure to use instead of FakeTableStructure
     * @param DbAdapterInterface|null $connection
     * @param array $interfaces - full class names of interfaces that fake table must implement
     * @param array $traits - full names of traits that fake table must use
     * @param string $classBody
     * @return FakeTable
     * @throws \InvalidArgumentException
     */
    public static function makeNewFakeTable(
        string $tableName,
        $columnsOrTableStructure = null,
        ?DbAdapterInterface $connection = null,
        array $interfaces = [],
        array $traits = [],
        string $classBody = ''
    ) {
        $tableName = trim($tableName);
        if ($tableName === '' || !DbAdapter::isValidDbEntityName($tableName, false)) {
            throw new \InvalidArgumentException(
                '$tableName argument must be a not empty string that matches DB entity naming rules (usually alphanumeric with underscores)'
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
        /** @var FakeTable $table */
        $table = $fullClassName::getInstance();
        $table->tableName = $tableName;
        if (is_array($columnsOrTableStructure)) {
            $table->getTableStructure()
                ->setTableColumns($columnsOrTableStructure);
        } elseif ($columnsOrTableStructure instanceof TableStructureInterface) {
            $table->setTableStructureToCopy($columnsOrTableStructure);
        } elseif ($columnsOrTableStructure !== null) {
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
     * @param bool $writable - true: connection must have access to write data into DB
     * @return DbAdapterInterface
     */
    public static function getConnection(bool $writable = false): DbAdapterInterface
    {
        return static::getInstance()->connection ?: parent::getConnection($writable);
    }
    
    /**
     * @return static
     */
    public function setConnection(DbAdapterInterface $connection)
    {
        $this->connection = $connection;
        return $this;
    }
    
    public function getTableStructure(): FakeTableStructure
    {
        if (!$this->tableStructure) {
            $this->tableStructure = FakeTableStructure::makeNewFakeStructure($this->tableName, $this->tableStructureToCopy);
        }
        return $this->tableStructure;
    }
    
    /**
     * @param TableStructureInterface $tableStructure
     * @return static
     */
    public function setTableStructureToCopy(TableStructureInterface $tableStructure)
    {
        $this->tableStructureToCopy = $tableStructure;
        return $this;
    }
    
    /**
     * @return RecordInterface
     */
    public function newRecord(): RecordInterface
    {
        if (!$this->recordClass) {
            $this->recordClass = FakeRecord::makeNewFakeRecordClass($this);
        }
        return new $this->recordClass();
    }
    
    /**
     * @param string $class
     * @return static
     * @throws \InvalidArgumentException
     */
    public function setRecordClass($class)
    {
        if ($class instanceof RecordInterface) {
            $this->recordClass = get_class($class);
        } elseif (!class_exists($class) || !in_array(RecordInterface::class, class_implements($class), true)) {
            throw new \InvalidArgumentException('$class argument is not a class or it does not implement RecordInterface');
        } else {
            $this->recordClass = $class;
        }
        return $this;
    }
}