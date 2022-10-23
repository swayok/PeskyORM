<?php

declare(strict_types=1);

namespace PeskyORM\ORM;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
use PeskyORM\TableDescription\ColumnDescription;
use PeskyORM\TableDescription\DescribeTable;
use PeskyORM\TableDescription\TableDescription;
use Swayok\Utils\StringUtils;

class ClassBuilder
{
    
    protected string $tableName;
    protected DbAdapterInterface $connection;
    
    protected ?string $dbSchemaName = null;
    protected ?TableDescription $tableDescription = null;
    
    private ?array $typeValueToTypeConstantName = null;
    
    public function __construct(string $tableName, DbAdapterInterface $connection)
    {
        $this->tableName = $tableName;
        $this->connection = $connection;
    }
    
    public function setDbSchemaName(string $schema): ClassBuilder
    {
        $this->dbSchemaName = $schema;
        return $this;
    }
    
    public function buildTableClass(string $namespace, ?string $parentClass = null): string
    {
        if ($parentClass === null) {
            $parentClass = Table::class;
        }
        $alias = StringUtils::classify($this->tableName);
        return <<<VIEW
<?php

namespace {$namespace};

use {$parentClass};

class {$this::makeTableClassName($this->tableName)} extends {$this->getShortClassName($parentClass)}
{

    public function getTableStructure(): {$this::makeTableStructureClassName($this->tableName)}
    {
        return {$this::makeTableStructureClassName($this->tableName)}::getInstance();
    }

    public function newRecord(): {$this::makeRecordClassName($this->tableName)}
    {
        return new {$this::makeRecordClassName($this->tableName)}();
    }

    public function getTableAlias(): string
    {
        return '{$alias}';
    }

}

VIEW;
    }
    
    /**
     * @param string $namespace
     * @param null|string $parentClass
     * @param array $traitsForColumns = [NameOfTrait1::class, NameOfTrait2::class]
     * @return string
     */
    public function buildStructureClass(string $namespace, ?string $parentClass = null, array $traitsForColumns = []): string
    {
        if ($parentClass === null) {
            $parentClass = TableStructure::class;
        }
        [$traits, $includes, $usedColumns] = $this->makeTraitsForTableStructure($traitsForColumns);
        $getSchemaMethod = '';
        if ($this->dbSchemaName && $this->dbSchemaName !== $this->connection->getDefaultTableSchema()) {
            $getSchemaMethod = <<<VIEW
        
    public static function getSchema(): ?string
    {
        return '{$this->dbSchemaName}';
    }
    
VIEW;
        }
        return <<<VIEW
<?php

namespace {$namespace};

use {$parentClass};
use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORM\Core\DbExpr;$includes

/**
{$this->makePhpDocForTableStructure()}
 */
class {$this::makeTableStructureClassName($this->tableName)} extends {$this->getShortClassName($parentClass)}
{
{$traits}
    public static function getTableName(): string
    {
        return '{$this->tableName}';
    }
$getSchemaMethod
{$this->makeColumnsMethodsForTableStructure($usedColumns)}

}

VIEW;
    }
    
    public function buildRecordClass(string $namespace, ?string $parentClass = null): string
    {
        if ($parentClass === null) {
            $parentClass = Record::class;
        }
        $includes = '';
        if ($this->hasDateOrTimestampColumns()) {
            $includes .= "\nuse Carbon\Carbon;";
        }
        return <<<VIEW
<?php

namespace {$namespace};

use {$parentClass};$includes

/**
{$this->makePhpDocForRecord()}
 */
class {$this::makeRecordClassName($this->tableName)} extends {$this->getShortClassName($parentClass)}
{

    public static function getTable(): {$this::makeTableClassName($this->tableName)}
    {
        return {$this::makeTableClassName($this->tableName)}::getInstance();
    }

}

VIEW;
    }
    
    protected function getTableDescription(): TableDescription
    {
        if (!$this->tableDescription) {
            $this->tableDescription = DescribeTable::getTableDescription($this->connection, $this->tableName, $this->dbSchemaName);
        }
        return $this->tableDescription;
    }
    
    public static function convertTableNameToClassName(string $tableName): string
    {
        return StringUtils::classify($tableName);
    }
    
    public static function makeTableClassName(string $tableName): string
    {
        return static::convertTableNameToClassName($tableName) . 'Table';
    }
    
    public static function makeTableStructureClassName(string $tableName): string
    {
        return static::convertTableNameToClassName($tableName) . 'TableStructure';
    }
    
    public static function makeRecordClassName(string $tableName): string
    {
        return StringUtils::singularize(static::convertTableNameToClassName($tableName));
    }
    
    protected function getShortClassName(string $fullClassName): string
    {
        return basename(str_replace('\\', '/', $fullClassName));
    }
    
    /**
     * @param array $traitsForColumns
     * @return array = [traits:string, class_includes:string, used_columns:array]
     */
    protected function makeTraitsForTableStructure(array $traitsForColumns): array
    {
        if (empty($traitsForColumns)) {
            return ['', '', []];
        }
        $traitsForColumns = array_unique($traitsForColumns);
        $traits = [];
        $classesToInclude = [];
        $usedColumns = [];
        $columnsNames = array_keys(
            $this->getTableDescription()
                ->getColumns()
        );
        foreach ($traitsForColumns as $traitClass) {
            $traitMethods = (new \ReflectionClass($traitClass))->getMethods(\ReflectionMethod::IS_PRIVATE);
            if (empty($traitMethods)) {
                continue;
            }
            $traitColumns = [];
            foreach ($traitMethods as $reflectionMethod) {
                if (preg_match(Column::NAME_VALIDATION_REGEXP, $reflectionMethod->getName())) {
                    $traitColumns[] = $reflectionMethod->getName();
                }
            }
            if (count(array_intersect($usedColumns, $traitColumns)) > 0) {
                // at least one of $traitColumns already replaced by trait
                continue;
            }
            if (!empty($traitColumns) && count(array_intersect($columnsNames, $traitColumns)) === count($traitColumns)) {
                $classesToInclude[] = $traitClass;
                $traits[] = $this->getShortClassName($traitClass);
                foreach ($traitColumns as $traitColumnName) {
                    $usedColumns[] = $traitColumnName;
                }
            }
        }
        $usedColumns = array_unique($usedColumns);
        return [
            count($usedColumns) ? "\n    use " . implode(",\n        ", $traits) . ";\n" : '',
            count($usedColumns) ? "\nuse " . implode(";\nuse ", $classesToInclude) . ';' : '',
            $usedColumns,
        ];
    }
    
    /**
     * @param array $excludeColumns - columns to exclude (already included via traits)
     * @return string
     */
    protected function makeColumnsMethodsForTableStructure(array $excludeColumns = []): string
    {
        $columns = [];
        foreach (
            $this->getTableDescription()
                ->getColumns() as $columnDescription
        ) {
            if (in_array($columnDescription->getName(), $excludeColumns, true)) {
                continue;
            }
            $columns[] = <<<VIEW
    private function {$columnDescription->getName()}(): Column
    {
        return {$this->makeColumnConfig($columnDescription)};
    }
VIEW;
        }
        return implode("\n\n", $columns);
    }
    
    /**
     * @param ColumnDescription $columnDescription
     * @return string
     */
    protected function makeColumnConfig(ColumnDescription $columnDescription): string
    {
        $ret = "Column::create({$this->getConstantNameForColumnType($columnDescription->getOrmType())})";
        if ($columnDescription->isPrimaryKey()) {
            $ret .= "\n            ->primaryKey()";
        }
        if ($columnDescription->isUnique()) {
            $ret .= "\n            ->uniqueValues()";
        }
        if (!$columnDescription->isNullable()) {
            $ret .= "\n            ->disallowsNullValues()";
            $ret .= "\n            ->convertsEmptyStringToNull()";
        }
        $default = $columnDescription->getDefault();
        if ($default !== null && !$columnDescription->isPrimaryKey()) {
            if ($default instanceof DbExpr) {
                $default = "DbExpr::create('" . addslashes(
                        $default->setWrapInBrackets(false)
                            ->get()
                    ) . "')";
            } elseif (is_string($default)) {
                $default = "'" . addslashes($default) . "'";
            } elseif (is_bool($default)) {
                $default = $default ? 'true' : 'false';
            }
            $ret .= "\n            ->setDefaultValue({$default})";
        }
        return $ret;
    }
    
    /**
     * @param string $columnTypeValue - like 'string', 'integer', etc..
     * @return string like Column::TYPE_*
     */
    protected function getConstantNameForColumnType(string $columnTypeValue): string
    {
        if ($this->typeValueToTypeConstantName === null) {
            $this->typeValueToTypeConstantName = array_flip(
                array_filter(
                    (new \ReflectionClass(Column::class))->getConstants(),
                    function ($key) {
                        return str_starts_with($key, 'TYPE_');
                    },
                    ARRAY_FILTER_USE_KEY
                )
            );
        }
        return 'Column::' . $this->typeValueToTypeConstantName[$columnTypeValue];
    }
    
    protected function makePhpDocForRecord(): string
    {
        $description = $this->getTableDescription();
        $getters = [];
        $setters = [];
        foreach ($description->getColumns() as $columnDescription) {
            $phpTypes = str_pad($this->getPhpTypeByColumnDescription($columnDescription), 11, ' ', STR_PAD_RIGHT);
            $getters[] = " * @property-read {$phpTypes} \${$columnDescription->getName()}";
            foreach ($this->getFormattersForOrmType($columnDescription->getOrmType()) as $formaterName => $phpType) {
                $phpType = str_pad($phpType, 11, ' ', STR_PAD_RIGHT);
                $getters[] = " * @property-read {$phpType} \${$columnDescription->getName()}_as_{$formaterName}";
            }
            $setter = 'set' . StringUtils::classify($columnDescription->getName());
            $setters[] = " * @method \$this    {$setter}(\$value, \$isFromDb = false)";
        }
        return implode("\n", $getters) . "\n *\n" . implode("\n", $setters);
    }
    
    protected function hasDateOrTimestampColumns(): bool
    {
        $description = $this->getTableDescription();
        $types = [
            Column::TYPE_TIMESTAMP,
            Column::TYPE_TIMESTAMP_WITH_TZ,
            Column::TYPE_UNIX_TIMESTAMP,
            Column::TYPE_DATE,
        ];
        foreach ($description->getColumns() as $columnDescription) {
            if (in_array($columnDescription->getOrmType(), $types, true)) {
                return true;
            }
        }
        return false;
    }
    
    protected function getPhpTypeByColumnDescription(ColumnDescription $columnDescription): string
    {
        $type = match ($columnDescription->getOrmType()) {
            Column::TYPE_INT => 'int',
            Column::TYPE_FLOAT => 'float',
            Column::TYPE_BOOL => 'bool',
            default => 'string',
        };
        return ($columnDescription->isNullable() ? 'null|' : '') . $type;
    }
    
    /**
     * @return array - key: format name; value: php type
     */
    protected function getFormattersForOrmType(string $ormType): array
    {
        $formats = RecordValueFormatters::getFormattersForColumnType($ormType);
        $formatToPhpType = [];
        foreach ($formats as $formatName => $formatterClosure) {
            $formatToPhpType[$formatName] = match ($formatName) {
                'unix_ts' => 'int',
                'array' => 'array',
                'object' => \stdClass::class,
                'carbon' => 'Carbon',
                default => 'string',
            };
        }
        return $formatToPhpType;
    }
    
    protected function makePhpDocForTableStructure(): string
    {
        $description = $this->getTableDescription();
        $getters = [];
        foreach ($description->getColumns() as $columnDescription) {
            $getters[] = " * @property-read Column    \${$columnDescription->getName()}";
        }
        return implode("\n", $getters);
    }
    
}