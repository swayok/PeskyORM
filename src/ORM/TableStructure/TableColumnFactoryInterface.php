<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;
use PeskyORM\TableDescription\ColumnDescription;
use PeskyORM\TableDescription\ColumnDescriptionInterface;
use PeskyORM\TableDescription\TableDescribers\MysqlTableDescriber;
use PeskyORM\TableDescription\TableDescribers\PostgresTableDescriber;

interface TableColumnFactoryInterface
{
    public function createFromDescription(
        ColumnDescriptionInterface $description
    ): TableColumnInterface;

    public function findClassNameForDescription(
        ColumnDescription $description
    ): string;

    /**
     * @param string $type - one of ColumnDescriptionDataType constants or DB type
     * @param string $class - must implement TableColumnInterface
     * @see ColumnDescriptionDataType
     * @see TableColumnInterface
     * @see MysqlTableDescriber::$dbTypeToOrmType for list of DB types
     * @see PostgresTableDescriber::$dbTypeToOrmType for list of DB types
     */
    public function mapTypeToColumnClass(string $type, string $class): void;

    /**
     * @param string $name - column name
     * @param string|null $class - must implement TableColumnInterface; null: remove mapping
     * @see TableColumnInterface
     */
    public function mapNameToColumnClass(string $name, ?string $class): void;
}