<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use PeskyORM\ORM\TableStructure\TableColumn\Column\BlobColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\BooleanColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\DateColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\FloatColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IdColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IntegerColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\MixedJsonColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\StringColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TextColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimeColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimestampColumn;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\ORM\TableStructure\TableColumn\UniqueTableColumnInterface;
use PeskyORM\TableDescription\ColumnDescriptionDataType;
use PeskyORM\TableDescription\ColumnDescriptionInterface;

abstract class TableColumnFactory
{
    protected static array $map = [
        ColumnDescriptionDataType::INT => IntegerColumn::class,
        ColumnDescriptionDataType::FLOAT => FloatColumn::class,
        ColumnDescriptionDataType::BOOL => BooleanColumn::class,
        ColumnDescriptionDataType::STRING => StringColumn::class,
        ColumnDescriptionDataType::TEXT => TextColumn::class,
        ColumnDescriptionDataType::JSON => MixedJsonColumn::class,
        ColumnDescriptionDataType::TIMESTAMP => TimestampColumn::class,
        ColumnDescriptionDataType::TIMESTAMP_WITH_TZ => TimestampColumn::class,
        ColumnDescriptionDataType::DATE => DateColumn::class,
        ColumnDescriptionDataType::TIME => TimeColumn::class,
        ColumnDescriptionDataType::TIME_TZ => TimeColumn::class,
        ColumnDescriptionDataType::BLOB => BlobColumn::class,
    ];

    protected static array $timezoneTypes = [
        ColumnDescriptionDataType::TIME_TZ,
        ColumnDescriptionDataType::TIMESTAMP_WITH_TZ,
    ];

    public static function createFromDescription(
        ColumnDescriptionInterface $description
    ): TableColumnInterface {
        $ormType = $description->getOrmType();
        $isPk = $description->isPrimaryKey();
        // ID column
        if (
            $isPk
            && $ormType === ColumnDescriptionDataType::INT
        ) {
            return new IdColumn($description->getName());
        }

        if (!isset(static::$map[$ormType])) {
            throw new \InvalidArgumentException(
                "There is no table column class for data type '{$ormType}'"
            );
        }
        $className = static::$map[$ormType];
        /** @var RealTableColumnAbstract $column */
        $column = new $className($description->getName());
        // primary key
        if ($isPk) {
            if (method_exists($column, 'primaryKey')) {
                $column->primaryKey();
            } else {
                throw new \UnexpectedValueException(
                    "Column '{$description->getName()}' is primary key"
                    . " but column class {$className} has no method 'primaryKey'"
                );
            }
        }
        // timezone
        if (in_array($ormType, static::$timezoneTypes, true)) {
            if (method_exists($column, 'withTimezone')) {
                $column->withTimezone();
            } else {
                throw new \UnexpectedValueException(
                    "Column '{$description->getName()}' should have timezone"
                    . " but column class {$className} has no method 'withTimezone'"
                );
            }
        }
        // nullable
        if ($description->isNullable()) {
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
        if ($default !== null) {
            $column->setDefaultValue($default);
        }
        // unique
        if ($description->isUnique()) {
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
        return $column;
    }

    /**
     * @param string $type - one of ColumnDescriptionDataType constants
     * @param string $class - must implement TableColumnInterface
     * @see ColumnDescriptionDataType
     */
    public static function mapTypeToColumnClass(string $type, string $class): void
    {
        static::$map[$type] = $class;
    }
}