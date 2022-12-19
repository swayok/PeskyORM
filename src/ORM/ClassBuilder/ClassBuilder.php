<?php

declare(strict_types=1);

namespace PeskyORM\ORM\ClassBuilder;

use Carbon\CarbonImmutable;
use PeskyORM\DbExpr;
use PeskyORM\ORM\Record\Record;
use PeskyORM\ORM\Table\Table;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimeColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimestampColumn;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\ORM\TableStructure\TableColumn\UniqueTableColumnInterface;
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

    protected array $timezoneTypes = [
        ColumnDescriptionDataType::TIME_TZ,
        ColumnDescriptionDataType::TIMESTAMP_WITH_TZ,
    ];

    public function __construct(
        protected TableDescriptionInterface $tableDescription,
        protected TableColumnFactoryInterface $columnFactory,
        protected string $namespace,
    ) {
    }

    public function buildTableClass(?string $parentClass = null): string
    {
        $namespace = $this->namespace;
        $parentClass = $this->getParentClass(static::TEMPLATE_TABLE, $parentClass);
        $parentClassName = $this->extractClassNameFromFQN($parentClass);
        $className = $this->getClassName(static::TEMPLATE_TABLE);
        $tableStructureClassName = $this->getClassName(static::TEMPLATE_TABLE_STRUCTURE);
        $recordClassName = $this->getClassName(static::TEMPLATE_RECORD);
        $tableAlias = $this->getTableAlias();
        try {
            ob_start();
            include $this->getTemplate(static::TEMPLATE_TABLE);
            return ob_get_clean();
        } catch (\Throwable $exception) {
            ob_clean();
            throw $exception;
        }
    }

    public function buildRecordClass(?string $parentClass = null): string
    {
        $namespace = $this->namespace;
        $parentClass = $this->getParentClass(static::TEMPLATE_RECORD, $parentClass);
        $parentClassName = $this->extractClassNameFromFQN($parentClass);
        $className = $this->getClassName(static::TEMPLATE_RECORD);
        $tableClassName = $this->getClassName(static::TEMPLATE_TABLE);
        $properties = $this->getRecordProperties();
        $setters = $this->getRecordSetterMethods();
        $includes = [];
        foreach ($properties as $name => &$type) {
            if (str_contains($type, '\\')) {
                $includes[] = $type;
                $type = $this->extractClassNameFromFQN($type);
            }
        }
        unset($type);
        $includes = array_unique($includes);
        try {
            ob_start();
            include $this->getTemplate(static::TEMPLATE_RECORD);
            return ob_get_clean();
        } catch (\Throwable $exception) {
            ob_clean();
            throw $exception;
        }
    }

    public function buildStructureClass(?string $parentClass = null): string
    {
        $namespace = $this->namespace;
        $parentClass = $this->getParentClass(static::TEMPLATE_TABLE_STRUCTURE, $parentClass);
        $parentClassName = $this->extractClassNameFromFQN($parentClass);
        $className = $this->getClassName(static::TEMPLATE_TABLE_STRUCTURE);
        $tableName = $this->tableDescription->getTableName();
        $tableSchema = $this->tableDescription->getDbSchema();
        $includes = [];
        $columns = $this->getColumnsDetailsForStructure();
        foreach ($columns as &$details) {
            if (str_contains($details['class'], '\\')) {
                $includes[] = $details['class'];
                $details['class'] = $this->extractClassNameFromFQN($details['class']);
            }
        }
        unset($details);
        $includes = array_unique($includes);
        try {
            ob_start();
            include $this->getTemplate(static::TEMPLATE_TABLE_STRUCTURE);
            return ob_get_clean();
        } catch (\Throwable $exception) {
            ob_clean();
            throw $exception;
        }
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
            ColumnValueFormatters::FORMAT_CARBON => CarbonImmutable::class,
            ColumnValueFormatters::FORMAT_UNIX_TS => 'int',
            default => 'string',
        };
    }

    protected function getTableAlias(): string
    {
        return StringUtils::toPascalCase($this->tableDescription->getTableName());
    }

    protected function extractClassNameFromFQN(string $class): string
    {
        return preg_replace('%^.*\\\([^\\\]+)$%', '$1', $class);
    }

    protected function getColumnsDetailsForStructure(): array
    {
        $columsDetails = [];
        foreach ($this->tableDescription->getColumns() as $description) {
            $className = $this->columnFactory->findClassNameForDescription($description);
            $columsDetails[] = [
                'name' => $description->getName(),
                'class' => $className,
                'addons' => $this->getAddonsForTableStructureColumn(
                    $description,
                    new $className($description->getName())
                ),
            ];
        }
        return $columsDetails;
    }

    protected function getAddonsForTableStructureColumn(
        ColumnDescriptionInterface $description,
        TableColumnInterface $column
    ): array {
        $addons = [];
        $className = get_class($column);
        // primary key
        if ($description->isPrimaryKey() && !$column->isPrimaryKey()) {
            if (method_exists($column, 'primaryKey')) {
                $addons['primaryKey'] = ['name' => 'primaryKey'];
            } else {
                throw new \UnexpectedValueException(
                    "Column '{$description->getName()}' is primary key"
                    . " but column class {$className} has no method 'primaryKey'"
                );
            }
        }
        // nullable
        if ($description->isNullable() && !$column->isNullableValues()) {
            if (method_exists($column, 'allowsNullValues')) {
                $addons['allowsNullValues'] = ['name' => 'allowsNullValues'];
            } else {
                throw new \UnexpectedValueException(
                    "Column '{$description->getName()}' should be nullable"
                    . " but column class {$className} has no method 'allowsNullValues'"
                );
            }
        }
        // default
        $default = $description->getDefault();
        if (
            $default !== null
            && !$column->hasDefaultValue()
            && !$column->isPrimaryKey()
        ) {
            $addons['setDefaultValue'] = [
                'name' => 'setDefaultValue',
                'arguments' => [$this->prepareDefaultValueArgumentForColumnAddon($default)],
            ];
        }
        // convert empty string to null if column has default value
        if (
            method_exists($column, 'convertsEmptyStringValuesToNull')
            && (
                $column->hasDefaultValue()
                || $default
            )
        ) {
            $addons['convertsEmptyStringValuesToNull'] = [
                'name' => 'convertsEmptyStringValuesToNull',
            ];
        }
        // unique
        if ($description->isUnique() && !$column->isValueMustBeUnique()) {
            if ($column instanceof UniqueTableColumnInterface) {
                $addons['uniqueValues'] = ['name' => 'uniqueValues'];
            } else {
                throw new \UnexpectedValueException(
                    "Column '{$description->getName()}' should be unique"
                    . " but column class {$className} does not implement "
                    . UniqueTableColumnInterface::class
                );
            }
        }
        // timezone
        if (in_array($description->getOrmType(), $this->timezoneTypes, true)) {
            /** @var TimestampColumn|TimeColumn $column */
            if (method_exists($column, 'withTimezone')) {
                $addons['withTimezone'] = ['name' => 'withTimezone'];
            } elseif (
                !method_exists($column, 'isTimezoneExpected')
                || !$column->isTimezoneExpected()
            ) {
                throw new \UnexpectedValueException(
                    "Column '{$description->getName()}' should have timezone"
                    . " but column class {$className} has no method 'withTimezone'"
                );
            }
        }
        return $addons;
    }

    protected function prepareDefaultValueArgumentForColumnAddon(mixed $default): string
    {
        if ($default instanceof DbExpr) {
            return "DbExpr::create('"
                . addslashes($default->setWrapInBrackets(false)->get())
                . "')";
        }
        if (is_string($default)) {
            return "'" . addslashes($default) . "'";
        }
        if (is_bool($default)) {
            return $default ? 'true' : 'false';
        }
        // number
        return $default;
    }

}