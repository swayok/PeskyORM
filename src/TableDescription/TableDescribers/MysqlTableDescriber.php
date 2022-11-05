<?php

declare(strict_types=1);

namespace PeskyORM\TableDescription\TableDescribers;

use JetBrains\PhpStorm\ArrayShape;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use PeskyORM\Core\Utils\PdoUtils;
use PeskyORM\ORM\Column;
use PeskyORM\TableDescription\ColumnDescription;
use PeskyORM\TableDescription\TableDescription;
use Swayok\Utils\ValidateValue;

class MysqlTableDescriber implements TableDescriberInterface
{

    protected static array $dbTypeToOrmType = [
        'bool' => Column::TYPE_BOOL,
        'blob' => Column::TYPE_BLOB,
        'tinyblob' => Column::TYPE_BLOB,
        'mediumblob' => Column::TYPE_BLOB,
        'longblob' => Column::TYPE_BLOB,
        'tinyint' => Column::TYPE_INT,
        'smallint' => Column::TYPE_INT,
        'mediumint' => Column::TYPE_INT,
        'bigint' => Column::TYPE_INT,
        'int' => Column::TYPE_INT,
        'integer' => Column::TYPE_INT,
        'decimal' => Column::TYPE_FLOAT,
        'dec' => Column::TYPE_FLOAT,
        'float' => Column::TYPE_FLOAT,
        'double' => Column::TYPE_FLOAT,
        'double precision' => Column::TYPE_FLOAT,
        'char' => Column::TYPE_STRING,
        'binary' => Column::TYPE_STRING,
        'varchar' => Column::TYPE_STRING,
        'varbinary' => Column::TYPE_STRING,
        'enum' => Column::TYPE_STRING,
        'set' => Column::TYPE_STRING,
        'text' => Column::TYPE_TEXT,
        'tinytext' => Column::TYPE_TEXT,
        'mediumtext' => Column::TYPE_TEXT,
        'longtext' => Column::TYPE_TEXT,
        'json' => Column::TYPE_JSON,
        'date' => Column::TYPE_DATE,
        'time' => Column::TYPE_TIME,
        'datetime' => Column::TYPE_TIMESTAMP,
        'timestamp' => Column::TYPE_TIMESTAMP,
        'year' => Column::TYPE_INT,
    ];

    public function __construct(private DbAdapterInterface $adapter)
    {
    }

    public function getTableDescription(string $tableName, ?string $schema = null): TableDescription
    {
        $description = new TableDescription($tableName, $schema ?? $this->adapter->getDefaultTableSchema());
        /** @var array $columns */
        $columns = $this->adapter->query(DbExpr::create("SHOW COLUMNS IN `$tableName`"), PdoUtils::FETCH_ALL);
        foreach ($columns as $columnInfo) {
            $columnDescription = new ColumnDescription(
                $columnInfo['Field'],
                $columnInfo['Type'],
                $this->convertDbTypeToOrmType($columnInfo['Type'])
            );
            $limitAndPrecision = $this->extractLimitAndPrecisionForColumnDescription($columnInfo['Type']);
            $columnDescription
                ->setLimitAndPrecision($limitAndPrecision['limit'], $limitAndPrecision['precision'])
                ->setIsNullable(strtolower($columnInfo['Null']) === 'yes')
                ->setIsPrimaryKey(strtolower($columnInfo['Key']) === 'pri')
                ->setIsUnique(strtolower($columnInfo['Key']) === 'uni')
                ->setDefault($this->cleanDefaultValueForColumnDescription($columnInfo['Default']));
            $description->addColumn($columnDescription);
        }
        return $description;
    }

    protected function convertDbTypeToOrmType(string $dbType): string
    {
        $dbType = strtolower(preg_replace(['%\s*unsigned$%i', '%\([^)]+\)$%'], ['', ''], $dbType));
        return array_key_exists($dbType, static::$dbTypeToOrmType)
            ? static::$dbTypeToOrmType[$dbType]
            : Column::TYPE_STRING;
    }

    protected function cleanDefaultValueForColumnDescription(
        DbExpr|float|array|bool|int|string|null $default
    ): DbExpr|float|array|bool|int|string|null {
        if ($default === null || $default === '') {
            return $default;
        }

        if ($default === 'CURRENT_TIMESTAMP') {
            return DbExpr::create('NOW()');
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

        if (ValidateValue::isFloat($default)) {
            return (float)$default;
        }

        return $default; //< it seems like there is still no possibility to use functions as default value
    }

    #[ArrayShape([
        'limit' => "null|int",
        'precision' => "null|int",
    ])]
    protected function extractLimitAndPrecisionForColumnDescription(string $typeDescription): array
    {
        if (preg_match('%\((\d+)(?:,(\d+))?\)( unsigned)?$%', $typeDescription, $matches)) {
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