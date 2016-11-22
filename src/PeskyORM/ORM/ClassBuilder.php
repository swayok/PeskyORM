<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\ColumnDescription;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
use Swayok\Utils\StringUtils;

class ClassBuilder {

    /**
     * @var string
     */
    protected $tableName;
    /**
     * @var DbAdapterInterface
     */
    protected $connection;

    public function __construct($tableName, DbAdapterInterface $connection) {
        $this->tableName = $tableName;
        $this->connection = $connection;
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
     * @return string
     */
    public function buildStructureClass($namespace, $parentClass = null) {
        if ($parentClass === null) {
            $parentClass = TableStructure::class;
        }
        return <<<VIEW
<?php

namespace {$namespace};

use {$parentClass};
use PeskyORM\ORM\Column;
use PeskyORM\ORM\Relation;
use PeskyORM\Core\DbExpr;

class {$this::makeTableStructureClassName($this->tableName)} extends {$this->getShortClassName($parentClass)} {

    static public function getTableName() {
        return '{$this->tableName}';
    }

{$this->makeColumnsMethodsForTableStructure()}

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
        return end(explode('\\', $fullClassName));
    }

    /**
     * @return string
     */
    protected function makeColumnsMethodsForTableStructure() {
        $description = $this->connection->describeTable($this->tableName);

        $columns = [];
        foreach ($description->getColumns() as $columnDescription) {

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
            $ret .= "\n            ->itIsPrimaryKey()";
        }
        if ($columnDescription->isUnique()) {
            $ret .= "\n            ->valueMustBeUnique(true)";
        }
        if (!$columnDescription->isNullable()) {
            $ret .= "\n            ->valueIsNotNullable()";
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