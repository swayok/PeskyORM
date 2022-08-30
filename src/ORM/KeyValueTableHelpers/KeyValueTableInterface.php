<?php

declare(strict_types=1);

namespace PeskyORM\ORM\KeyValueTableHelpers;

use PeskyORM\ORM\RecordInterface;
use PeskyORM\ORM\TableInterface;

interface KeyValueTableInterface extends TableInterface
{
    
    public function getMainForeignKeyColumnName(): ?string;
    
    /**
     * Make array that represents DB record and can be saved to DB
     */
    public static function makeDataForRecord(string $key, mixed $value, float|int|string|null $foreignKeyValue = null): array;
    
    /**
     * Convert associative array to arrays that represent DB record and are ready for saving to DB
     * @param array $settingsAssoc - associative array of settings
     * @param float|int|string|null $foreignKeyValue
     * @param array $additionalConstantValues - contains constant values for all records (for example: admin id)
     */
    public static function convertToDataForRecords(
        array $settingsAssoc,
        float|int|string|null $foreignKeyValue = null,
        array $additionalConstantValues = []
    ): array;
    
    /**
     * Update existing value or create new one
     * @param array $data - must contain: key, foreign_key, value
     */
    public static function updateOrCreateRecord(array $data): RecordInterface;
    
    /**
     * Update existing values and create new
     */
    public static function updateOrCreateRecords(array $records): void;
    
    /**
     * @param string $key
     * @param float|int|string|null $foreignKeyValue - use null if there is no main foreign key column and
     *      getMainForeignKeyColumnName() method returns null
     * @param mixed|null $default
     */
    public static function getValue(string $key, float|int|string|null $foreignKeyValue = null, mixed $default = null): mixed;
    
    public static function getValuesForForeignKey(float|int|string|null $foreignKeyValue = null): array;
    
}