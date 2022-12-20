<?php

declare(strict_types=1);

namespace PeskyORM\TableDescription\TableDescribers;

use JetBrains\PhpStorm\ArrayShape;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\DbExpr;
use PeskyORM\TableDescription\ColumnDescription;
use PeskyORM\TableDescription\ColumnDescriptionDataType;
use PeskyORM\TableDescription\TableDescription;
use PeskyORM\TableDescription\TableDescriptionInterface;
use PeskyORM\Utils\PdoUtils;
use PeskyORM\Utils\ValueTypeValidators;

class MysqlTableDescriber implements TableDescriberInterface
{
    protected static array $dbTypeToOrmType = [
        'bool' => ColumnDescriptionDataType::BOOL,
        'blob' => ColumnDescriptionDataType::BLOB,
        'tinyblob' => ColumnDescriptionDataType::BLOB,
        'mediumblob' => ColumnDescriptionDataType::BLOB,
        'longblob' => ColumnDescriptionDataType::BLOB,
        'tinyint' => ColumnDescriptionDataType::INT,
        'smallint' => ColumnDescriptionDataType::INT,
        'mediumint' => ColumnDescriptionDataType::INT,
        'bigint' => ColumnDescriptionDataType::INT,
        'int' => ColumnDescriptionDataType::INT,
        'integer' => ColumnDescriptionDataType::INT,
        'decimal' => ColumnDescriptionDataType::FLOAT,
        'dec' => ColumnDescriptionDataType::FLOAT,
        'float' => ColumnDescriptionDataType::FLOAT,
        'double' => ColumnDescriptionDataType::FLOAT,
        'double precision' => ColumnDescriptionDataType::FLOAT,
        'char' => ColumnDescriptionDataType::STRING,
        'binary' => ColumnDescriptionDataType::STRING,
        'varchar' => ColumnDescriptionDataType::STRING,
        'varbinary' => ColumnDescriptionDataType::STRING,
        'enum' => ColumnDescriptionDataType::STRING,
        'set' => ColumnDescriptionDataType::STRING,
        'text' => ColumnDescriptionDataType::TEXT,
        'tinytext' => ColumnDescriptionDataType::TEXT,
        'mediumtext' => ColumnDescriptionDataType::TEXT,
        'longtext' => ColumnDescriptionDataType::TEXT,
        'json' => ColumnDescriptionDataType::JSON,
        'date' => ColumnDescriptionDataType::DATE,
        'time' => ColumnDescriptionDataType::TIME,
        'datetime' => ColumnDescriptionDataType::TIMESTAMP,
        'timestamp' => ColumnDescriptionDataType::TIMESTAMP,
        'year' => ColumnDescriptionDataType::INT,
    ];

    public function __construct(private DbAdapterInterface $adapter)
    {
    }

    public function getTableDescription(string $tableName, ?string $schema = null): TableDescriptionInterface
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
            : ColumnDescriptionDataType::STRING;
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

        if (ValueTypeValidators::isInteger($default)) {
            return (int)$default;
        }

        if (is_numeric($default)) {
            return (float)$default;
        }

        return $default;
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