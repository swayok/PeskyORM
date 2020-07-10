<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\ColumnDescription;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\TableDescription;
use Swayok\Utils\StringUtils;

class ClassBuilder {

    /**
     * @var string
     */
    protected $tableName;
    /**
     * @var
     */
    protected $dbSchemaName;
    /**
     * @var DbAdapterInterface
     */
    protected $connection;
    /**
     * @var TableDescription
     */
    protected $tableDescription;

    public function __construct($tableName, DbAdapterInterface $connection) {
        $this->tableName = $tableName;
        $this->connection = $connection;
    }

    /**
     * @param $schema
     */
    public function setDbSchemaName($schema) {
        $this->dbSchemaName = $schema;
    }

    /**
     * @param string $namespace
     * @param null|string $parentClass
     * @return string
     */
    public function buildTableClass($namespace, $parentClass = null) {
        if ($parentClass === null) {
            $parentClass = Table::class;
        }
        $alias = StringUtils::classify($this->tableName);
        return <<<VIEW
<?php

namespace {$namespace};

use {$parentClass};

class {$this::makeTableClassName($this->tableName)} extends {$this->getShortClassName($parentClass)} {

    public function getTableStructure(): {$this::makeTableStructureClassName($this->tableName)} {
        return {$this::makeTableStructureClassName($this->tableName)}::getInstance();
    }

    public function newRecord(): {$this::makeRecordClassName($this->tableName)} {
        return new {$this::makeRecordClassName($this->tableName)}();
    }

    public function getTableAlias(): string {
        return '{$alias}';
    }

}

VIEW;
    }

    /**
     * @param string $namespace
     * @param null|string $parentClass
     * @param array $traitsForColumns - array (
     *      NameOfTrait1::class,
     *      NameOfTrait2::class,
     * )
     * @return string
     */
    public function buildStructureClass($namespace, $parentClass = null, array $traitsForColumns = []) {
        if ($parentClass === null) {
            $parentClass = TableStructure::class;
        }
        [$traits, $includes, $usedColumns] = $this->makeTraitsForTableStructure($traitsForColumns);
        $getSchemaMethod = '';
        if ($this->dbSchemaName && $this->dbSchemaName !== $this->connection->getDefaultTableSchema()) {
            $getSchemaMethod = <<<VIEW
        
    static public function getSchema(): ?string {
        return '{$this->dbSchemaName}';
    }
    
VIEW;
        }
        return <<<VIEW
<?php

namespace {$namespace};

use {$parentClass};
use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORM\Core\DbExpr;$includes

/**
{$this->makePhpDocForTableStructure()}
 */
class {$this::makeTableStructureClassName($this->tableName)} extends {$this->getShortClassName($parentClass)} {
{$traits}
    static public function getTableName(): string {
        return '{$this->tableName}';
    }
$getSchemaMethod
{$this->makeColumnsMethodsForTableStructure($usedColumns)}

}

VIEW;
    }

    /**
     * @param string $namespace
     * @param null|string $parentClass
     * @return string
     */
    public function buildRecordClass($namespace, $parentClass = null) {
        if ($parentClass === null) {
            $parentClass = Record::class;
        }
        return <<<VIEW
<?php

namespace {$namespace};

use {$parentClass};

/**
{$this->makePhpDocForRecord()}
 */
class {$this::makeRecordClassName($this->tableName)} extends {$this->getShortClassName($parentClass)} {

    static public function getTable(): {$this::makeTableClassName($this->tableName)} {
        return {$this::makeTableClassName($this->tableName)}::getInstance();
    }

}

VIEW;
    }

    /**
     * @return TableDescription
     */
    protected function getTableDescription() {
        if (!$this->tableDescription) {
            $this->tableDescription = $this->connection->describeTable($this->tableName, $this->dbSchemaName);
        }
        return $this->tableDescription;
    }

    /**
     * @param string $tableName
     * @return string
     */
    static public function convertTableNameToClassName($tableName) {
        return StringUtils::classify($tableName);
    }

    /**
     * @param string $tableName
     * @return string
     */
    static public function makeTableClassName($tableName) {
        return static::convertTableNameToClassName($tableName) . 'Table';
    }

    /**
     * @param string $tableName
     * @return string
     */
    static public function makeTableStructureClassName($tableName) {
        return static::convertTableNameToClassName($tableName) . 'TableStructure';
    }

    /**
     * @param string $tableName
     * @return string
     */
    static public function makeRecordClassName($tableName) {
        return StringUtils::singularize(static::convertTableNameToClassName($tableName));
    }

    /**
     * @param string $fullClassName
     * @return mixed
     */
    protected function getShortClassName($fullClassName) {
        return basename(str_replace('\\', '/', $fullClassName));
    }

    /**
     * @param array $traitsForColumns
     * @return array - [traits:string, class_includes:string, used_columns:array]
     */
    protected function makeTraitsForTableStructure(array $traitsForColumns) {
        if (empty($traitsForColumns)) {
            return ['', '', []];
        }
        $traitsForColumns = array_unique($traitsForColumns);
        $traits = [];
        $classesToInclude = [];
        $usedColumns = [];
        $columnsNames = array_keys($this->getTableDescription()->getColumns());
        foreach ($traitsForColumns as $traitClass) {
            $traitMethods = (new \ReflectionClass($traitClass))->getMethods(\ReflectionMethod::IS_PRIVATE);
            if (empty($traitMethods)) {
                continue;
            }
            $traitColumns = [];
            foreach ($traitMethods as $reflectionMethod) {
                if (preg_match(Column::NAME_VALIDATION_REGEXP, $reflectionMethod->getName())) {
                    $traitColumns[] = $reflectionMethod->getName();
                }
            }
            if (count(array_intersect($usedColumns, $traitColumns)) > 0) {
                // at least one of $traitColumns already replaced by trait
                continue;
            }
            if (!empty($traitColumns) && count(array_intersect($columnsNames, $traitColumns)) === count($traitColumns)) {
                $classesToInclude[] = $traitClass;
                $traits[] = $this->getShortClassName($traitClass);
                foreach ($traitColumns as $traitColumnName) {
                    $usedColumns[] = $traitColumnName;
                }
            }
        }
        $usedColumns = array_unique($usedColumns);
        return [
            count($usedColumns) ? "\n    use " . implode(",\n        ", $traits) . ";\n" : '',
            count($usedColumns) ? "\nuse " . implode(";\nuse ", $classesToInclude). ';' : '',
            $usedColumns
        ];
    }

    /**
     * @param array $excludeColumns - columns to exclude (already included via traits)
     * @return string
     */
    protected function makeColumnsMethodsForTableStructure(array $excludeColumns = []) {
        $columns = [];
        foreach ($this->getTableDescription()->getColumns() as $columnDescription) {
            if (in_array($columnDescription->getName(), $excludeColumns, true)) {
                continue;
            }
            $columns[] = <<<VIEW
    private function {$columnDescription->getName()}(): Column {
        return {$this->makeColumnConfig($columnDescription)};
    }
VIEW;

        }
        return implode("\n\n", $columns);
    }

    /**
     * @param ColumnDescription $columnDescription
     * @return string
     */
    protected function makeColumnConfig(ColumnDescription $columnDescription) {
        $ret = "Column::create({$this->getConstantNameForColumnType($columnDescription->getOrmType())})";
        if ($columnDescription->isPrimaryKey()) {
            $ret .= "\n            ->primaryKey()";
        }
        if ($columnDescription->isUnique()) {
            $ret .= "\n            ->uniqueValues()";
        }
        if (!$columnDescription->isNullable()) {
            $ret .= "\n            ->disallowsNullValues()";
            $ret .= "\n            ->convertsEmptyStringToNull()";
        }
        $default = $columnDescription->getDefault();
        if ($default !== null && !$columnDescription->isPrimaryKey()) {
            if ($default instanceof DbExpr) {
                $default = "DbExpr::create('" . addslashes($default->setWrapInBrackets(false)->get()) . "')";
            } else if (is_string($default)) {
                $default = "'" . addslashes($default) . "'";
            } else if (is_bool($default)) {
                $default = $default ? 'true' : 'false';
            }
            $ret .= "\n            ->setDefaultValue({$default})";
        }
        return $ret;
    }

    private $typeValueToTypeConstantName;
    /**
     * @param string $columnTypeValue - like 'string', 'integer', etc..
     * @return string like Column::TYPE_*
     */
    protected function getConstantNameForColumnType($columnTypeValue) {
        if ($this->typeValueToTypeConstantName === null) {
            $this->typeValueToTypeConstantName = array_flip(
                array_filter(
                    (new \ReflectionClass(Column::class))->getConstants(),
                    function ($key) {
                        return strpos($key, 'TYPE_') === 0;
                    },
                    ARRAY_FILTER_USE_KEY
                )
            );
        }
        return 'Column::' . $this->typeValueToTypeConstantName[$columnTypeValue];
    }

    /**
     * @return string
     */
    protected function makePhpDocForRecord() {
        $description = $this->getTableDescription();
        $getters = [];
        $setters = [];
        foreach ($description->getColumns() as $columnDescription) {
            $phpTypes = str_pad($this->getPhpTypeByColumnDescription($columnDescription), 11, ' ', STR_PAD_RIGHT);
            $getters[] = " * @property-read {$phpTypes} \${$columnDescription->getName()}";
            foreach ($this->getFormattersForOrmType($columnDescription->getOrmType()) as $formaterName => $phpType) {
                $phpType = str_pad($phpType, 11, ' ', STR_PAD_RIGHT);
                $getters[] = " * @property-read {$phpType} \${$columnDescription->getName()}_as_{$formaterName}";
            }
            $setter = 'set' . StringUtils::classify($columnDescription->getName());
            $setters[] = " * @method \$this    {$setter}(\$value, \$isFromDb = false)";
        }
        return implode("\n", $getters) . "\n *\n" . implode("\n", $setters);
    }

    /**
     * @param ColumnDescription $columnDescription
     * @return string
     */
    protected function getPhpTypeByColumnDescription(ColumnDescription $columnDescription) {
        switch ($columnDescription->getOrmType()) {
            case Column::TYPE_INT:
                $type = 'int';
                break;
            case Column::TYPE_FLOAT:
                $type = 'float';
                break;
            case Column::TYPE_BOOL:
                $type = 'bool';
                break;
            default:
                $type = 'string';
        }
        return ($columnDescription->isNullable() ? 'null|' : '') . $type;
    }

    /**
     * @param $ormType
     * @return array - key: format name; value: php type
     */
    protected function getFormattersForOrmType($ormType) {
        $formats = RecordValueHelpers::getValueFormatterAndFormatsByType($ormType)[1];
        $formatToPhpType = [];
        foreach ($formats as $formatName) {
            switch ($formatName) {
                case 'unix_ts':
                    $formatToPhpType[$formatName] = 'int';
                    break;
                case 'array':
                    $formatToPhpType[$formatName] = 'array';
                    break;
                case 'object':
                    $formatToPhpType[$formatName] = '\stdClass';
                    break;
                default:
                    $formatToPhpType[$formatName] = 'string';
            }
        }
        return $formatToPhpType;
    }

    /**
     * @return string
     */
    protected function makePhpDocForTableStructure() {
        $description = $this->getTableDescription();
        $getters = [];
        foreach ($description->getColumns() as $columnDescription) {
            $getters[] = " * @property-read Column    \${$columnDescription->getName()}";
        }
        return implode("\n", $getters);
    }

}