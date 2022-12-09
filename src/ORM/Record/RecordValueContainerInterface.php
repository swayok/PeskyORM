<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Record;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

interface RecordValueContainerInterface
{
    public const PAYLOAD_KEY_FOR_VALUE_SAVING_EXTENDER = 'saving_extender';

    public function getColumn(): TableColumnInterface;

    public function getRecord(): RecordInterface;

    public function isItFromDb(): bool;

    public function setIsFromDb(bool $isFromDb): void;

    public function hasValue(): bool;

    /**
     * @throws \BadMethodCallException when value not set
     */
    public function getValue(): mixed;

    public function getRawValue(): mixed;

    /**
     * @param mixed $rawValue - value as is
     * @param mixed $processedValue - value processed using TableColumnInterface options
     * @param bool $isFromDb - is value received from DB or not
     */
    public function setValue(
        mixed $rawValue,
        mixed $processedValue,
        bool $isFromDb
    ): void;

    /**
     * Get all payload values or value for single key.
     * @param string|null $key -
     *      null: return all payload data;
     *      string: return payload for key or $default if no payload stored
     * @param mixed $default - used when there are no value for $key
     */
    public function getPayload(?string $key = null, mixed $default = null): mixed;

    /**
     * Check if there is a payload for key
     */
    public function hasPayload(string $key): bool;

    /**
     * Get payload for key and remove it.
     * Use this for single use payloads like data for
     * value saving extender.
     */
    public function pullPayload(string $key, mixed $default = null): mixed;

    /**
     * Get payload for key or use $default closure to
     * create new value for key and save it.
     * @see self::addPayload()
     */
    public function rememberPayload(
        string $key,
        \Closure $default = null
    ): mixed;

    /**
     * Add a payload for key.
     * Use payloads to store formatted values or some data
     * that should persist until value is changed.
     * You also can store some single use data like data for
     * value saving extender.
     * Value saving extender is called after value was saved to DB.
     * It is needed for deferred actions like saving uploaded files to FS
     * after Record was already saved to DB and knows its PK value.
     */
    public function addPayload(string $key, mixed $value): void;

    /**
     *  @param string|null $key -
     *      null: delete all payload data;
     *      string: delete payload for key
     */
    public function removePayload(?string $key = null): void;

    /**
     * Collects all value-related properties.
     * You should exclude objects like TableColumnInterface and RecordInterface from result.
     * May be used to serialize RecordInterface instance.
     */
    public function toArray(): array;

    /**
     * Sets all value-related properties from $data.
     * May be used to unserialize RecordInterface instance.
     */
    public function fromArray(array $data): void;
}