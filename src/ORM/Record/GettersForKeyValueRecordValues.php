<?php

declare(strict_types=1);

namespace PeskyORM\ORM\Record;

use PeskyORM\ORM\Table\KeyValueTableInterface;

/**
 * @psalm-require-implements \PeskyORM\ORM\Record\RecordInterface
 * todo: refactor key-value helper classes and system to be more clear
 */
trait GettersForKeyValueRecordValues
{
    public static function get(
        string $key,
        float|int|string|null $foreignKeyValue = null,
        mixed $default = null
    ): mixed {
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
