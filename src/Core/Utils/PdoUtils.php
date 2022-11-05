<?php

declare(strict_types=1);

namespace PeskyORM\Core\Utils;

abstract class PdoUtils
{
    public const FETCH_ALL = 'all';
    public const FETCH_FIRST = 'first';
    public const FETCH_VALUE = 'value';
    public const FETCH_COLUMN = 'column';
    public const FETCH_KEY_PAIR = 'key_pair';
    public const FETCH_STATEMENT = 'statement';
    public const FETCH_ROWS_COUNT = 'rows_count';

    /**
     * Get data from $statement according to required $type
     * @param \PDOStatement $statement
     * @param string $type = one of \PeskyORM\Core\FETCH_*
     * @return mixed|\PDOStatement|null
     * @throws \InvalidArgumentException
     */
    public static function getDataFromStatement(\PDOStatement $statement, string $type = self::FETCH_ALL): mixed
    {
        $type = strtolower($type);
        if ($type === self::FETCH_STATEMENT) {
            return $statement;
        }

        if ($type === self::FETCH_ROWS_COUNT) {
            return $statement->rowCount();
        }

        if ($statement->rowCount() === 0) {
            return $type === self::FETCH_VALUE ? null : [];
        }

        return match ($type) {
            self::FETCH_COLUMN => $statement->fetchAll(\PDO::FETCH_COLUMN),
            self::FETCH_KEY_PAIR => $statement->fetchAll(\PDO::FETCH_KEY_PAIR),
            self::FETCH_VALUE => $statement->fetchColumn(),
            self::FETCH_FIRST => $statement->fetch(\PDO::FETCH_ASSOC),
            self::FETCH_ALL => $statement->fetchAll(\PDO::FETCH_ASSOC),
            default => throw new \InvalidArgumentException("Unknown processing type [{$type}]"),
        };
    }

    public static function isValidDbEntityName(string $name): bool
    {
        return preg_match('%^[a-zA-Z_][a-zA-Z_0-9]*(\.[a-zA-Z_0-9]+|\.\*)?$%i', $name) > 0;
    }
}