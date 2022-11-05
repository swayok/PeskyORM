<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\Utils;
use PeskyORM\Core\Utils\PdoUtils;
use PeskyORM\ORM\Traits\FakeTableStructureHelpers;
use Swayok\Utils\StringUtils;

abstract class FakeTableStructure extends TableStructure
{
    
    use FakeTableStructureHelpers;
    
    private static int $fakesCreated = 0;
    protected bool $treatAnyColumnNameAsValid = true;
    protected string $connectionName = 'default';
    protected string $connectionNameWritable = 'default';
    
    /**
     * @param string $tableName
     * @param TableStructureInterface|null $tableStructureToCopy - use this table structure as parent class for a fake one
     *      but replace its table name
     * @return TableStructureInterface
     * @throws \InvalidArgumentException
     */
    public static function makeNewFakeStructure(
        string $tableName,
        ?TableStructureInterface $tableStructureToCopy = null
    ): TableStructureInterface {
        $tableName = trim($tableName);
        if ($tableName === '' || !PdoUtils::isValidDbEntityName($tableName)) {
            throw new \InvalidArgumentException(
                '$tableName argument must be a not empty string that matches DB entity naming rules (usually alphanumeric with underscores)'
            );
        }
        static::$fakesCreated++;
        if ($tableStructureToCopy) {
            $parentClassFullName = get_class($tableStructureToCopy);
            $classReflection = new \ReflectionClass($tableStructureToCopy);
            $namespace = $classReflection->getNamespaceName();
            $parentClassShortName = $classReflection->getShortName();
            $dbSchema = 'null';
        } else {
            $namespace = 'PeskyORM\ORM\Fakes';
            $parentClassFullName = __CLASS__;
            $parentClassShortName = 'FakeTableStructure';
            $dbSchema = 'parent::getSchema()';
        }
        $className = 'FakeTableStructure' . static::$fakesCreated . 'For' . StringUtils::classify($tableName);
        
        $class = <<<VIEW
namespace {$namespace};

use {$parentClassFullName};
use PeskyORM\ORM\Traits\FakeTableStructureHelpers;

class {$className} extends {$parentClassShortName}
{

    use FakeTableStructureHelpers;
    
    /**
     * @return string
     */
    public static function getTableName(): string {
        return '{$tableName}';
    }

    /**
     * @return string
     */
    public static function getSchema(): ?string {
        return {$dbSchema};
    }
    
}
VIEW;
        eval($class);
        /** @var FakeTableStructure $fullClassName */
        $fullClassName = $namespace . '\\' . $className;
        return $fullClassName::getInstance();
    }
    
    protected function loadConfigs(): void
    {
        $this->pk = $this->columns['id'] = Column::create(Column::TYPE_INT, 'id')->primaryKey();
        parent::loadConfigs();
    }
    
    /**
     * @param bool $writable - true: connection must have access to write data into DB
     * @return string
     */
    public static function getConnectionName(bool $writable): string
    {
        return $writable ? static::getInstance()->connectionNameWritable : static::getInstance()->connectionName;
    }
    
    /**
     * @param string $columnName
     * @return bool
     */
    public static function hasColumn(string $columnName): bool
    {
        return static::getInstance()->treatAnyColumnNameAsValid || parent::hasColumn($columnName);
    }
    
    /**
     * @param string $columnName
     * @return Column
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \BadMethodCallException
     */
    public static function getColumn(string $columnName): Column
    {
        if (static::getInstance()->treatAnyColumnNameAsValid && !parent::hasColumn($columnName)) {
            static::getInstance()->columns[$columnName] = Column::create(Column::TYPE_STRING, $columnName);
        }
        return parent::getColumn($columnName);
    }
    
    protected function loadColumnsAndRelationsFromPrivateMethods(): void
    {
    }
    
    protected function createMissingColumnsConfigsFromDbTableDescription(): void
    {
    }
}
