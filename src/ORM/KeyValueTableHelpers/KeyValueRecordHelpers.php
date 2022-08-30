<?php

declare(strict_types=1);

namespace PeskyORM\ORM\KeyValueTableHelpers;

/**
 * @psalm-require-implements \PeskyORM\ORM\RecordInterface
 */
trait KeyValueRecordHelpers
{
    
    public static function get(string $key, float|int|string|null $foreignKeyValue = null, mixed $default = null): mixed
    {
        /** @var KeyValueTableInterface $table */
        $table = static::getTable();
        return $table->getValue($key, $foreignKeyValue, $default);
    }
    
    public static function __callStatic(string $key, array $arguments): mixed
    {
        $fkValue = $arguments[0] ?? null;
        $default = $arguments[1] ?? null;
        return static::get($key, $fkValue, $default);
    }
    
    
}
