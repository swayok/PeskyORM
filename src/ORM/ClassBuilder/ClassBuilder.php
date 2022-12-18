<?php

declare(strict_types=1);

namespace PeskyORM\ORM\ClassBuilder;

use Carbon\CarbonImmutable;
use PeskyORM\DbExpr;
use PeskyORM\ORM\Record\Record;
use PeskyORM\ORM\Table\Table;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\ORM\TableStructure\TableColumnFactoryInterface;
use PeskyORM\ORM\TableStructure\TableStructure;
use PeskyORM\TableDescription\ColumnDescriptionDataType;
use PeskyORM\TableDescription\ColumnDescriptionInterface;
use PeskyORM\TableDescription\TableDescriptionInterface;
use PeskyORM\Utils\StringUtils;

class ClassBuilder
{
    public const TEMPLATE_TABLE = 'table';
    public const TEMPLATE_TABLE_STRUCTURE = 'table_structure';
    public const TEMPLATE_RECORD = 'record';

    public function __construct(
        protected TableDescriptionInterface $tableDescription,
        protected TableColumnFactoryInterface $columnFactory,
        protected string $namespace,
    ) {
    }

    public function buildTableClass(?string $parentClass = null): string
    {
        $parentClass = $this->getParentClass(static::TEMPLATE_TABLE, $parentClass);
        $namespace = $this->namespace;
        $tableAlias = $this->getTableAlias();
        $className = $this->getClassName(static::TEMPLATE_TABLE);
        $tableStructureClassName = $this->getClassName(static::TEMPLATE_TABLE_STRUCTURE);
        $recordClassName = $this->getClassName(static::TEMPLATE_RECORD);

        ob_start();
        include $this->getTemplate(static::TEMPLATE_TABLE);
        return ob_get_clean();
    }

    public function buildRecordClass(?string $parentClass = null): string
    {
        $parentClass = $this->getParentClass(static::TEMPLATE_RECORD, $parentClass);
        $namespace = $this->namespace;
        $className = $this->getClassName(static::TEMPLATE_RECORD);
        $tableClassName = $this->getClassName(static::TEMPLATE_TABLE);
        $includes = [];
        $properties = $this->getRecordProperties();
        $setters = $this->getRecordSetterMethods();
        ob_start();
        include $this->getTemplate(static::TEMPLATE_RECORD);
        return ob_get_clean();
    }

    public function buildStructureClass(?string $parentClass = null): string
    {
        $parentClass = $this->getParentClass(static::TEMPLATE_TABLE_STRUCTURE, $parentClass);
        $namespace = $this->namespace;
        [$traits, $includes, $usedColumns] = $this->makeTraitsForTableStructure($traitsForColumns);
        $getSchemaMethod = '';
        if ($this->tableSchema && $this->tableSchema !== $this->connection->getDefaultTableSchema()) {
            $getSchemaMethod = <<<VIEW
        
    public static function getSchema(): ?string
    {
        return '{$this->tableSchema}';
    }
    
VIEW;
        }
        return <<<VIEW
<?php

namespace {$namespace};

use {$parentClass};
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\DbExpr;$includes

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

    protected function getTemplate(string $type): string
    {
        return match ($type) {
            static::TEMPLATE_TABLE => __DIR__ . '/templates/table.php',
            static::TEMPLATE_TABLE_STRUCTURE => __DIR__ . '/templates/table_structure.php',
            static::TEMPLATE_RECORD => __DIR__ . '/templates/record.php',
            default => throw new \InvalidArgumentException('Unknown template type: ' . $type),
        };
    }

    protected function getParentClass(string $type, ?string $parentClass): string
    {
        if ($parentClass) {
            return $parentClass;
        }
        return match ($type) {
            static::TEMPLATE_TABLE => Table::class,
            static::TEMPLATE_TABLE_STRUCTURE => TableStructure::class,
            static::TEMPLATE_RECORD => Record::class,
            default => throw new \InvalidArgumentException('Unknown template type: ' . $type),
        };
    }

    protected function getClassName(string $type): string
    {
        $tableName = $this->tableDescription->getTableName();
        return match ($type) {
            static::TEMPLATE_TABLE =>
                StringUtils::toPascalCase($tableName) . 'Table',
            static::TEMPLATE_TABLE_STRUCTURE =>
                StringUtils::toPascalCase($tableName) . 'TableStructure',
            static::TEMPLATE_RECORD =>
            StringUtils::toSingularPascalCase($tableName),
            default =>
            throw new \InvalidArgumentException('Unknown template type: ' . $type),
        };
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
        $columnsNames = array_keys($this->tableDescription->getColumns());
        foreach ($traitsForColumns as $traitClass) {
            $traitMethods = (new \ReflectionClass($traitClass))->getMethods(\ReflectionMethod::IS_PRIVATE);
            if (empty($traitMethods)) {
                continue;
            }
            $traitColumns = [];
            foreach ($traitMethods as $reflectionMethod) {
                if (StringUtils::isSnakeCase($reflectionMethod->getName())) {
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
        foreach ($this->getTableDescription()->getColumns() as $columnDescription) {
            if (in_array($columnDescription->getName(), $excludeColumns, true)) {
                continue;
            }
            $columns[] = <<<VIEW
    private function {$columnDescription->getName()}(): TableColumnInterface
    {
        return {$this->makeColumnConfig($columnDescription)};
    }
VIEW;
        }
        return implode("\n\n", $columns);
    }

    protected function makeColumnConfig(ColumnDescriptionInterface $columnDescription): string
    {
        $ret = "TableColumn::create({$this->getConstantNameForColumnType($columnDescription->getOrmType())})";
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

    protected function getRecordProperties(): array
    {
        $ret = [];
        foreach ($this->tableDescription->getColumns() as $columnDescription) {
            $column = $this->columnFactory->createFromDescription($columnDescription);
            $ret[$column->getName()] = $this->getPhpTypeForColumn($column);
            $formatters = $column->getValueFormatersNames();
            foreach ($formatters as $columnName => $format) {
                $ret[$columnName] = $this->getPhpTypeByFormatterName($format);
            }
        }
        return $ret;
    }

    private function getRecordSetterMethods(): array
    {
        $ret = [];
        foreach ($this->tableDescription->getColumns() as $columnDescription) {
            $ret[] = 'set' . StringUtils::toPascalCase($columnDescription->getName());
        }
        return $ret;
    }

    protected function hasDateTimeColumns(): bool
    {
        $types = [
            ColumnDescriptionDataType::TIMESTAMP,
            ColumnDescriptionDataType::TIMESTAMP_WITH_TZ,
            ColumnDescriptionDataType::DATE,
            ColumnDescriptionDataType::TIME,
            ColumnDescriptionDataType::TIME_TZ,
        ];
        foreach ($this->tableDescription->getColumns() as $columnDescription) {
            if (in_array($columnDescription->getOrmType(), $types, true)) {
                return true;
            }
        }
        return false;
    }

    protected function getPhpTypeForColumn(TableColumnInterface $column): string
    {
        $type = match ($column->getDataType()) {
            TableColumnDataType::INT,
            TableColumnDataType::UNIX_TIMESTAMP => 'int',
            TableColumnDataType::FLOAT => 'float',
            TableColumnDataType::BOOL => 'bool',
            TableColumnDataType::BLOB => 'resource',
            default => 'string',
        };
        return ($column->isNullableValues() ? 'null|' : '') . $type;
    }

    protected function getPhpTypeByFormatterName(string $format): string
    {
        return match ($format) {
            ColumnValueFormatters::FORMAT_ARRAY => 'array',
            ColumnValueFormatters::FORMAT_DECODED => 'mixed',
            ColumnValueFormatters::FORMAT_OBJECT => '\\' . \stdClass::class,
            ColumnValueFormatters::FORMAT_CARBON => '\\' . CarbonImmutable::class,
            ColumnValueFormatters::FORMAT_UNIX_TS => 'int',
            default => 'string',
        };
    }

    protected function getTableAlias(): string
    {
        return StringUtils::toPascalCase($this->tableDescription->getTableName());
    }
}