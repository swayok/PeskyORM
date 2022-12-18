<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use PeskyORM\ORM\TableStructure\TableColumn\Column\BlobColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\BooleanColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\CreatedAtColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\DateColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\FloatColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IdColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IntegerColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\MixedJsonColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\StringColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TextColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimeColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimestampColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimestampWithTimezoneColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimeWithTimezoneColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\UpdatedAtColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\ORM\TableStructure\TableColumn\UniqueTableColumnInterface;
use PeskyORM\TableDescription\ColumnDescription;
use PeskyORM\TableDescription\ColumnDescriptionDataType;
use PeskyORM\TableDescription\ColumnDescriptionInterface;
use PeskyORM\Utils\ArgumentValidators;

class TableColumnFactory implements TableColumnFactoryInterface
{
    protected array $typeToClass = [
        ColumnDescriptionDataType::INT => IntegerColumn::class,
        ColumnDescriptionDataType::FLOAT => FloatColumn::class,
        ColumnDescriptionDataType::BOOL => BooleanColumn::class,
        ColumnDescriptionDataType::STRING => StringColumn::class,
        ColumnDescriptionDataType::TEXT => TextColumn::class,
        ColumnDescriptionDataType::JSON => MixedJsonColumn::class,
        ColumnDescriptionDataType::TIMESTAMP => TimestampColumn::class,
        ColumnDescriptionDataType::TIMESTAMP_WITH_TZ => TimestampWithTimezoneColumn::class,
        ColumnDescriptionDataType::DATE => DateColumn::class,
        ColumnDescriptionDataType::TIME => TimeColumn::class,
        ColumnDescriptionDataType::TIME_TZ => TimeWithTimezoneColumn::class,
        ColumnDescriptionDataType::BLOB => BlobColumn::class,
    ];

    protected array $nameToClass = [
        'id' => IdColumn::class,
        'created_at' => CreatedAtColumn::class,
        'updated_at' => UpdatedAtColumn::class
    ];

    protected array $timezoneTypes = [
        ColumnDescriptionDataType::TIME_TZ,
        ColumnDescriptionDataType::TIMESTAMP_WITH_TZ,
    ];

    public function __construct(array $typeToClass = [], array $nameToClass = []) {
        foreach ($typeToClass as $type => $class) {
            $this->mapTypeToColumnClass($type, $class);
        }
        foreach ($nameToClass as $name => $class) {
            $this->mapNameToColumnClass($name, $class);
        }
    }

    public function createFromDescription(
        ColumnDescriptionInterface $description
    ): TableColumnInterface {
        $className = $this->findClassNameForDescription($description);
        /** @var RealTableColumnAbstract $column */
        $column = new $className($description->getName());

        // primary key
        if ($description->isPrimaryKey() && !$column->isPrimaryKey()) {
            if (method_exists($column, 'primaryKey')) {
                $column->primaryKey();
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
                $column->allowsNullValues();
            } else {
                throw new \UnexpectedValueException(
                    "Column '{$description->getName()}' should be nullable"
                    . " but column class {$className} has no method 'allowsNullValues'"
                );
            }
        }
        // default
        $default = $description->getDefault();
        if ($default !== null && !$column->isPrimaryKey()) {
            $column->setDefaultValue($default);
        }
        // unique
        if ($description->isUnique() && !$column->isValueMustBeUnique()) {
            if ($column instanceof UniqueTableColumnInterface) {
                $column->uniqueValues();
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
                $column->withTimezone();
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
        return $column;
    }

    public function findClassNameForDescription(
        ColumnDescription $description
    ): string {
        $name = $description->getName();
        if (isset($this->nameToClass[$name])) {
            return $this->nameToClass[$name];
        }

        $ormType = $description->getOrmType();
        if (isset($this->typeToClass[$ormType])) {
            return $this->typeToClass[$ormType];
        }

        throw new \InvalidArgumentException(
            "There is no table column class for data type '{$ormType}'"
            . " or column anme '$name'."
        );
    }

    public function mapTypeToColumnClass(string $type, string $class): void
    {
        ArgumentValidators::assertClassImplementsInterface(
            '$class',
            $class,
            TableColumnInterface::class
        );
        $this->typeToClass[$type] = $class;
    }

    public function mapNameToColumnClass(string $name, ?string $class): void
    {
        if (!$class) {
            unset($this->nameToClass[$name]);
            return;
        }
        ArgumentValidators::assertClassImplementsInterface(
            '$class',
            $class,
            TableColumnInterface::class
        );
        $this->nameToClass[$name] = $class;
    }
}