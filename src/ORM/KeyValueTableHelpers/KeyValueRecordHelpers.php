<?php

declare(strict_types=1);

namespace PeskyORM\ORM\KeyValueTableHelpers;

/**
 * @method static KeyValueTableInterface getTable()
 * @psalm-require-implements \PeskyORM\ORM\RecordInterface
 */
trait KeyValueRecordHelpers
{
    
    /**
     * @param string $key
     * @param mixed $foreignKeyValue
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $foreignKeyValue = null, $default = null)
    {
        return static::getTable()
            ->getValue($key, $foreignKeyValue, $default);
    }
    
    /**
     * @param string $key
     * @param array $arguments
     * @return mixed
     * @throws \InvalidArgumentException
     */
    public static function __callStatic(string $key, array $arguments)
    {
        $fkValue = $arguments[0] ?? null;
        $default = $arguments[1] ?? null;
        return static::get($key, $fkValue, $default);
    }
    
    
}
