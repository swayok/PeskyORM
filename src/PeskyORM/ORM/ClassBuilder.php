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
        return <<<VIEW
<?php

namespace {$namespace};

use {$parentClass};

class {$this::makeTableClassName($this->tableName)} extends {$this->getShortClassName($parentClass)} {

    /**
     * @return {$this::makeTableStructureClassName($this->tableName)}
     */
    public function getTableStructure() {
        return {$this::makeTableStructureClassName($this->tableName)}::getInstance();
    }

    /**
     * @return {$this::makeRecordClassName($this->tableName)}
     */
    public function newRecord() {
        return new {$this::makeRecordClassName($this->tableName)}();
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
        $schemaName = $this->dbSchemaName ? "'$this->dbSchemaName'" : 'null';
        $description = $this->connection->describeTable($this->tableName, $this->dbSchemaName);
        list($traits, $includes, $usedColumns) = $this->makeTraitsForTableStructure($description, $traitsForColumns);
        return <<<VIEW
<?php

namespace {$namespace};

use {$parentClass};
use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORM\Core\DbExpr;$includes

class {$this::makeTableStructureClassName($this->tableName)} extends {$this->getShortClassName($parentClass)} {
{$traits}
    static public function getTableName() {
        return '{$this->tableName}';
    }

    static public function getSchema() {
        return {$schemaName};
    }

{$this->makeColumnsMethodsForTableStructure($description, $usedColumns)}

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

class {$this::makeRecordClassName($this->tableName)} extends {$this->getShortClassName($parentClass)} {

    /**
     * @return {$this::makeTableClassName($this->tableName)}
     */
    static public function getTable() {
        return {$this::makeTableClassName($this->tableName)}::getInstance();
    }

}

VIEW;
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
     * @param TableDescription $description
     * @param array $traitsForColumns
     * @return array - [traits:string, class_includes:string, used_columns:array]
     */
    protected function makeTraitsForTableStructure(TableDescription $description, array $traitsForColumns) {
        if (empty($traitsForColumns)) {
            return ['', '', []];
        }
        $traitsForColumns = array_unique($traitsForColumns);
        $traits = [];
        $classesToInclude = [];
        $usedColumns = [];
        $columnsNames = array_keys($description->getColumns());
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
     * @param TableDescription $description
     * @param array $excludeColumns - columns to exclude (already included via traits)
     * @return string
     */
    protected function makeColumnsMethodsForTableStructure(TableDescription $description, array $excludeColumns = []) {
        $columns = [];
        foreach ($description->getColumns() as $columnDescription) {
            if (in_array($columnDescription->getName(), $excludeColumns, true)) {
                continue;
            }
            $columns[] = <<<VIEW
    private function {$columnDescription->getName()}() {
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
        } else {
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

    /**
     * @param string $columnTypeValue - like 'string', 'integer', etc..
     * @return string like Column::TYPE_*
     */
    protected function getConstantNameForColumnType($columnTypeValue) {
        static $typeValueToTypeConstantName = null;
        if ($typeValueToTypeConstantName === null) {
            $typeValueToTypeConstantName = array_flip(
                array_filter(
                    (new \ReflectionClass(Column::class))->getConstants(),
                    function ($key) {
                        return strpos($key, 'TYPE_') === 0;
                    },
                    ARRAY_FILTER_USE_KEY
                )
            );
        }
        return 'Column::' . $typeValueToTypeConstantName[$columnTypeValue];
    }

}