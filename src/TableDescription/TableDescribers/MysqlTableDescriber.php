<?php

declare(strict_types=1);

namespace PeskyORM\TableDescription\TableDescribers;

use JetBrains\PhpStorm\ArrayShape;
use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\TableDescription\ColumnDescription;
use PeskyORM\TableDescription\TableDescription;
use PeskyORM\Utils\PdoUtils;
use Swayok\Utils\ValidateValue;

class MysqlTableDescriber implements TableDescriberInterface
{

    protected static array $dbTypeToOrmType = [
        'bool' => TableColumn::TYPE_BOOL,
        'blob' => TableColumn::TYPE_BLOB,
        'tinyblob' => TableColumn::TYPE_BLOB,
        'mediumblob' => TableColumn::TYPE_BLOB,
        'longblob' => TableColumn::TYPE_BLOB,
        'tinyint' => TableColumn::TYPE_INT,
        'smallint' => TableColumn::TYPE_INT,
        'mediumint' => TableColumn::TYPE_INT,
        'bigint' => TableColumn::TYPE_INT,
        'int' => TableColumn::TYPE_INT,
        'integer' => TableColumn::TYPE_INT,
        'decimal' => TableColumn::TYPE_FLOAT,
        'dec' => TableColumn::TYPE_FLOAT,
        'float' => TableColumn::TYPE_FLOAT,
        'double' => TableColumn::TYPE_FLOAT,
        'double precision' => TableColumn::TYPE_FLOAT,
        'char' => TableColumn::TYPE_STRING,
        'binary' => TableColumn::TYPE_STRING,
        'varchar' => TableColumn::TYPE_STRING,
        'varbinary' => TableColumn::TYPE_STRING,
        'enum' => TableColumn::TYPE_STRING,
        'set' => TableColumn::TYPE_STRING,
        'text' => TableColumn::TYPE_TEXT,
        'tinytext' => TableColumn::TYPE_TEXT,
        'mediumtext' => TableColumn::TYPE_TEXT,
        'longtext' => TableColumn::TYPE_TEXT,
        'json' => TableColumn::TYPE_JSON,
        'date' => TableColumn::TYPE_DATE,
        'time' => TableColumn::TYPE_TIME,
        'datetime' => TableColumn::TYPE_TIMESTAMP,
        'timestamp' => TableColumn::TYPE_TIMESTAMP,
        'year' => TableColumn::TYPE_INT,
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
            : TableColumn::TYPE_STRING;
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