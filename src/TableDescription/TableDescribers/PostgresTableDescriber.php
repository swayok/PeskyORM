<?php

namespace PeskyORM\TableDescription\TableDescribers;

use JetBrains\PhpStorm\ArrayShape;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use PeskyORM\ORM\Column;
use PeskyORM\TableDescription\ColumnDescription;
use PeskyORM\TableDescription\TableDescription;
use Swayok\Utils\ValidateValue;

class PostgresTableDescriber implements TableDescriberInterface
{

    protected static array $dbTypeToOrmType = [
        'bool' => Column::TYPE_BOOL,
        'bytea' => Column::TYPE_BLOB,
        'bit' => Column::TYPE_BLOB,
        'varbit' => Column::TYPE_BLOB,
        'int8' => Column::TYPE_INT,
        'int2' => Column::TYPE_INT,
        'int4' => Column::TYPE_INT,
        'float4' => Column::TYPE_FLOAT,
        'float8' => Column::TYPE_FLOAT,
        'numeric' => Column::TYPE_FLOAT,
        'money' => Column::TYPE_FLOAT,
        'macaddr' => Column::TYPE_STRING,
        'inet' => Column::TYPE_STRING,       //< 192.168.0.0 or 192.168.0.0/24
        'cidr' => Column::TYPE_STRING,       //< 192.168.0.0/24 only
        'char' => Column::TYPE_STRING,
        'name' => Column::TYPE_STRING,
        'bpchar' => Column::TYPE_STRING,     //< blank-padded char == char, internal use but may happen
        'varchar' => Column::TYPE_STRING,
        'text' => Column::TYPE_TEXT,
        'xml' => Column::TYPE_STRING,
        'json' => Column::TYPE_JSON,
        'jsonb' => Column::TYPE_JSONB,
        'uuid' => Column::TYPE_STRING,
        'date' => Column::TYPE_DATE,
        'time' => Column::TYPE_TIME,
        'timestamp' => Column::TYPE_TIMESTAMP,
        'timestamptz' => Column::TYPE_TIMESTAMP_WITH_TZ,
        'interval' => Column::TYPE_STRING,
        'timetz' => Column::TYPE_TIME,
    ];

    public function __construct(private DbAdapterInterface $adapter)
    {
    }

    public function getTableDescription(string $tableName, ?string $schema = null): TableDescription
    {
        $description = new TableDescription($tableName, $schema ?? $this->adapter->getDefaultTableSchema());
        $query = $this->getSqlQuery($description);
        /** @var array $columns */
        $columns = $this->adapter->query(DbExpr::create($query), Utils::FETCH_ALL);
        foreach ($columns as $columnInfo) {
            $columnDescription = new ColumnDescription(
                $columnInfo['name'],
                $columnInfo['type'],
                $this->convertDbTypeToOrmType($columnInfo['type'])
            );
            $limitAndPrecision = $this->extractLimitAndPrecisionForColumnDescription($columnInfo['type_description']);
            $columnDescription
                ->setLimitAndPrecision($limitAndPrecision['limit'], $limitAndPrecision['precision'])
                ->setIsNullable(!$columnInfo['notnull'])
                ->setIsPrimaryKey($columnInfo['primarykey'])
                ->setIsForeignKey($columnInfo['foreignkey'])
                ->setIsUnique($columnInfo['uniquekey'])
                ->setDefault($this->cleanDefaultValueForColumnDescription($columnInfo['default']));
            $description->addColumn($columnDescription);
        }
        return $description;
    }

    protected function getSqlQuery(TableDescription $description): string
    {
        return "
            SELECT
                `f`.`attname` AS `name`,
                `f`.`attnotnull` AS `notnull`,
                `t`.`typname` AS `type`,
                `pg_catalog`.format_type(`f`.`atttypid`,`f`.`atttypmod`) as `type_description`,
                COALESCE(
                    (
                        SELECT true FROM `pg_constraint` as `pk`
                        WHERE `pk`.`conrelid` = `c`.`oid` AND `f`.`attnum` = ANY (`pk`.`conkey`) AND `pk`.`contype` = ``p``
                        LIMIT 1
                    ),
                    FALSE
                ) as `primarykey`,
                COALESCE(
                    (
                        SELECT true FROM `pg_constraint` as `uk`
                        WHERE `uk`.`conrelid` = `c`.`oid` AND `f`.`attnum` = ANY (`uk`.`conkey`) AND `uk`.`contype` = ``u``
                        LIMIT 1
                    ),
                    FALSE
                ) as `uniquekey`,
                COALESCE(
                    (
                        SELECT true FROM `pg_constraint` as `fk`
                        WHERE `fk`.`conrelid` = `c`.`oid` AND `f`.`attnum` = ANY (`fk`.`conkey`) AND `fk`.`contype` = ``f``
                        LIMIT 1
                    ),
                    FALSE
                ) as `foreignkey`,
                CASE
                    WHEN `f`.`atthasdef` = true THEN `d`.`adsrc`
                END AS `default`
            FROM pg_attribute f
                JOIN `pg_class` `c` ON `c`.`oid` = `f`.`attrelid`
                JOIN `pg_type` `t` ON `t`.`oid` = `f`.`atttypid`
                LEFT JOIN `pg_attrdef` `d` ON `d`.adrelid = `c`.`oid` AND `d`.`adnum` = `f`.`attnum`
                LEFT JOIN `pg_namespace` `n` ON `n`.`oid` = `c`.`relnamespace`
            WHERE `c`.`relkind` = ``r``::char
                AND `n`.`nspname` = ``{$description->getDbSchema()}``
                AND `c`.`relname` = ``{$description->getTableName()}``
                AND `f`.`attnum` > 0
            ORDER BY `f`.`attnum`
        ";
    }

    protected function convertDbTypeToOrmType(string $dbType): string
    {
        return array_key_exists($dbType, static::$dbTypeToOrmType)
            ? static::$dbTypeToOrmType[$dbType]
            : Column::TYPE_STRING;
    }

    protected function cleanDefaultValueForColumnDescription(
        DbExpr|float|array|bool|int|string|null $default
    ): DbExpr|float|array|bool|int|string|null {
        if ($default === null || $default === '' || preg_match('%^NULL::%i', $default)) {
            return null;
        }

        if (preg_match(
            "%^'((?:[^']|'')*?)'(?:::(bpchar|character varying|char|jsonb?|xml|macaddr|varchar|inet|cidr|text|uuid))?$%",
            $default,
            $matches
        )) {
            return str_replace("''", "'", $matches[1]);
        }

        if (preg_match(
            "%^'(\d+(?:\.\d*)?)'(?:::(numeric|decimal|(?:small|medium|big)?int(?:eger)?[248]?))?$%",
            $default,
            $matches
        )) {
            return (float)$matches[1];
        }

        if ($default === 'true') {
            return true;
        }

        if ($default === 'false') {
            return false;
        }

        if (ValidateValue::isInteger($default)) {
            return (int)$default;
        }

        if (($tmp = trim($default, "'")) !== '' && ValidateValue::isFloat($tmp)) {
            return (float)$tmp;
        }

        return DbExpr::create($default);
    }

    #[ArrayShape([
        'limit' => "null|int",
        'precision' => "null|int",
    ])]
    protected function extractLimitAndPrecisionForColumnDescription(string $typeDescription): array
    {
        if (preg_match('%\((\d+)(?:,(\d+))?\)$%', $typeDescription, $matches)) {
            return [
                'limit' => (int)$matches[1],
                'precision' => !isset($matches[2]) ? null : (int)$matches[2],
            ];
        }

        return [
            'limit' => null,
            'precision' => null,
        ];
    }
}