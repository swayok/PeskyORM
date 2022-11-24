<?php

declare(strict_types=1);

namespace PeskyORM\Utils;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\DbExpr;
use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\Select\SelectQueryBuilderInterface;

abstract class DbQuoter
{
    /**
     * Quote DB entity name (column, table, alias, schema).
     * Names format:
     *  1. 'table', 'column', 'TableAlias'
     *  2. 'TableAlias.column' - quoted like '`TableAlias`.`column`'
     * @param DbAdapterInterface $adapter
     * @param string $name - DB entity name to quote
     * @param string $dbEntityQuote - DB entity quotation symbol
     * @return string
     * @throws \InvalidArgumentException
     */
    public static function quoteDbEntityName(
        DbAdapterInterface $adapter,
        string $dbEntityQuote,
        string $name,
    ): string {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Db entity name must be a not empty string');
        }
        if ($name === '*') {
            return '*';
        }
        if (!$adapter->isValidDbEntityName($name)) {
            throw new \InvalidArgumentException("Invalid db entity name: [$name]");
        }
        if (preg_match('%[-#]>%', $name)) {
            // we've got a json selector like 'Alias.col_name->json_key1' 'Alias.col_name ->>json_key1',
            // 'Alias.col_name #> json_key1', 'Alias.col_name#>> json_key1', 'Alias.col_name->json_key1->>json_key2'
            $parts = preg_split('%\s*([-#]>>?)\s*%', $name, -1, PREG_SPLIT_DELIM_CAPTURE);
            return $adapter->quoteJsonSelectorExpression($parts);
        }

        return $dbEntityQuote . str_replace('.', $dbEntityQuote . '.' . $dbEntityQuote, $name) . $dbEntityQuote;
    }

    public static function quoteDbExpr(
        DbAdapterInterface $adapter,
        string $dbEntityQuote,
        DbExpr $expression,
        string $trueValue = '1',
        string $falseValue = '0',
    ): string {
        $quoted = preg_replace_callback(
            '%``(.*?)``%s',
            static function ($matches) use ($falseValue, $trueValue, $dbEntityQuote, $adapter) {
                return static::quoteValue($adapter, $dbEntityQuote, $matches[1], null, $trueValue, $falseValue);
            },
            $expression->get()
        );
        return preg_replace_callback(
            '%`(.*?)`%s',
            static function ($matches) use ($dbEntityQuote, $adapter) {
                return static::quoteDbEntityName($adapter, $dbEntityQuote, $matches[1]);
            },
            $quoted
        );
    }

    public static function quoteValue(
        DbAdapterInterface $adapter,
        string $dbEntityQuote,
        string|int|float|bool|array|DbExpr|RecordInterface|SelectQueryBuilderInterface|null $value,
        ?int $valueDataType = null,
        string $trueValue = '1',
        string $falseValue = '0',
    ): string {
        if (is_object($value)) {
            if ($value instanceof DbExpr) {
                return static::quoteDbExpr($adapter, $dbEntityQuote, $value);
            }

            if ($value instanceof SelectQueryBuilderInterface) {
                return '(' . $value->getQuery() . ')';
            }

            if ($value instanceof RecordInterface) {
                // use Record's primary key as value
                $value = $value->getPrimaryKeyValue();
            }
        }

        if ($value === null || $valueDataType === \PDO::PARAM_NULL) {
            return 'NULL';
        }

        if (($valueDataType === null && is_bool($value)) || $valueDataType === \PDO::PARAM_BOOL) {
            return $value ? $trueValue : $falseValue;
        }

        if ($valueDataType === null) {
            if (is_int($value)) {
                $valueDataType = \PDO::PARAM_INT;
            } else {
                $valueDataType = \PDO::PARAM_STR;
            }
        } elseif ($valueDataType === \PDO::PARAM_INT) {
            if (is_int($value) || (is_string($value) && is_numeric($value))) {
                $value = (int)$value;
            } else {
                if (is_string($value)) {
                    $realType = "String [$value]";
                } elseif (is_array($value)) {
                    $realType = 'Array';
                } elseif (is_object($value)) {
                    $realType = 'Object fo class [\\' . get_class($value) . ']';
                } elseif (is_bool($value)) {
                    $realType = 'Boolean [' . ($value ? 'true' : 'false') . ']';
                } else {
                    $realType = 'Value of unknown type';
                }
                throw new \InvalidArgumentException("\$value expected to be integer or numeric string. $realType received");
            }
        }
        if ($valueDataType === \PDO::PARAM_STR && is_string($value)) {
            // prevent "\" at the end of a string by duplicating slashes
            /** @noinspection RegExpSimplifiable */
            $value = preg_replace('%([\\\]+)$%', '$1$1', $value);
        }
        if (!in_array($valueDataType, [\PDO::PARAM_STR, \PDO::PARAM_INT, \PDO::PARAM_LOB], true)) {
            throw new \InvalidArgumentException('Value in $fieldType argument must be a constant like \PDO::PARAM_*');
        }
        if (is_array($value)) {
            $value = static::serializeArray($value);
        }
        return $adapter->getConnection()->quote((string)$value, $valueDataType);
    }

    /**
     * Convert passed $array to string compatible with sql query
     */
    private static function serializeArray(array $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }


}