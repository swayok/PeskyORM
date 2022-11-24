<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Table;

use PeskyORM\ORM\Record\RecordInterface;

// todo: refactor key-value helper classes and system to be more clear
interface KeyValueTableInterface extends TableInterface
{
    /**
     * Returns main foreign key column name. It is used to get/save key-value pairs.
     * Example: key-value table stores settings for company. In order to separate settings
     * between companies we need to have a column like 'company_id' as foreign key.
     * This methoud should then return 'company_id'.
     * If there is no foreign key column (global settings for example),
     * then this method should return null.
     */
    public static function getMainForeignKeyColumnName(): ?string;

    public static function getKeysColumnName(): string;

    public static function getValuesColumnName(): string;

    /**
     * Update existing record or create new one.
     * It should not matter if $record has primary key value or not - this method
     * must check if key+fk_value
     */
    public static function saveRecord(RecordInterface $record): RecordInterface;

    /**
     * Returns value for $key and $foreignKeyValue.
     * If there are no value - returns $default.
     * If there are no foreign key column in table - use $foreignKeyValue = null.
     * @see self::getMainForeignKeyColumnName() for foreign key column description
     */
    public static function getValue(
        string $key,
        float|int|string|null $foreignKeyValue = null,
        mixed $default = null
    ): mixed;

    /**
     * Returns all values for specified $foreignKeyValue.
     * If $foreignKeyValue === null - returns all values in table.
     */
    public static function getValuesForForeignKey(
        float|int|string|null $foreignKeyValue = null
    ): array;

}