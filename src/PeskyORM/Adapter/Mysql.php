<?php

namespace PeskyORM\Adapter;

use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Core\DbAdapter;
use PeskyORM\Core\DbException;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;

class Mysql extends DbAdapter {

    const VALUE_QUOTES = '"';
    const NAME_QUOTES = '`';

    static protected $dataTypesMap = [
        'bytea' => 'BINARY',
        'date' => 'DATE',
        'time' => 'TIME',
        'timestamp' => 'DATETIME',
        'timestamptz' => 'DATETIME',
        'timestamp with time zone' => 'DATETIME',
        'timestamp without time zone' => 'DATETIME',
        'decimal' => 'DECIMAL',
        'numeric' => 'DECIMAL',
        'real' => 'DECIMAL',
        'double precision' => 'DECIMAL',
        'int2' => 'SIGNED INTEGER',
        'smallint' => 'SIGNED INTEGER',
        'int4' => 'SIGNED INTEGER',
        'integer' => 'SIGNED INTEGER',
        'int8' => 'SIGNED INTEGER',
        'bigint' => 'SIGNED INTEGER',
    ];

    protected static $conditionOperatorsMap = [
        'SIMILAR TO' => 'LIKE',
        'NOT SIMILAR TO' => 'NOT LIKE',
        '~' => 'REGEXP',
        '!~' => 'NOT REGEXP',
        '~*' => 'REGEXP',
        '!~*' => 'NOT REGEXP',
        'REGEX' => 'REGEXP',
        'NOT REGEX' => 'NOT REGEXP',
    ];

    public function __construct(MysqlConfig $connectionConfig) {
        parent::__construct($connectionConfig);
    }

    public function isDbSupportsTableSchemas() {
        return false;
    }

    public function getDefaultTableSchema() {
        return null;
    }

    public function addDataTypeCastToExpression($dataType, $expression) {
        if (!is_string($dataType)) {
            throw new \InvalidArgumentException('$dataType must be a string');
        }
        if (!is_string($expression)) {
            throw new \InvalidArgumentException('$expression must be a string');
        }
        return 'CAST(' . $expression . ' AS ' . $this->getRealDataType($dataType) . ')';
    }

    protected function getRealDataType($dataType) {
        $dataType = strtolower($dataType);
        if (array_key_exists($dataType, static::$dataTypesMap)) {
            return static::$dataTypesMap[$dataType];
        } else {
            return 'CHAR';
        }
    }
    
    public function getConditionOperatorsMap() {
        return static::$conditionOperatorsMap;
    }

    protected function resolveQueryWithReturningColumns(
        $query,
        $table,
        array $columns,
        array $data,
        array $dataTypes,
        $returning,
        $pkName,
        $operation
    ) {
        $returning = $returning === true ? '*' : $this->buildColumnsList($returning, false);
        switch ($operation) {
            case 'insert':
                return $this->resolveInsertOneQueryWithReturningColumns($table, $data, $dataTypes, $returning, $pkName);
            case 'insert_many':
                return $this->resolveInsertManyQueryWithReturningColumns(
                    $table,
                    $columns,
                    $data,
                    $dataTypes,
                    $returning,
                    $pkName
                );
            case 'delete':
                return $this->resolveDeleteQueryWithReturningColumns($query, $table, $returning);
            case 'update':
                return $this->resolveUpdateQueryWithReturningColumns($query, $table, $returning);
            default:
                throw new \InvalidArgumentException("\$operation '$operation' is not supported by " . __CLASS__);
        }
    }

    protected function resolveInsertOneQueryWithReturningColumns(
        $table,
        array $data,
        array $dataTypes,
        $returning,
        $pkName
    ) {
        parent::insert($table, $data, $dataTypes, false);
        $insertQuery = $this->getLastQuery();
        $id = $this->quoteValue(
            Utils::getDataFromStatement($this->query('SELECT LAST_INSERT_ID()'),
            Utils::FETCH_VALUE
        ));
        $pkName = $this->quoteName($pkName);
        $query = DbExpr::create("SELECT {$returning} FROM {$table} WHERE $pkName=$id");
        $stmnt = $this->query($query);
        if (!$stmnt->rowCount()) {
            throw new DbException(
                'No data received for $returning request after insert. Insert: ' . $insertQuery
                    . '. Select: ' . $this->getLastQuery(),
                DbException::CODE_RETURNING_FAILED
            );
        } else if ($stmnt->rowCount() > 1) {
            throw new DbException(
                'Received more then 1 record for $returning request after insert. Insert: ' . $insertQuery
                    . '. Select: ' . $this->getLastQuery(),
                DbException::CODE_RETURNING_FAILED
            );
        }
        return Utils::getDataFromStatement($stmnt, Utils::FETCH_FIRST);
    }

    protected function resolveInsertManyQueryWithReturningColumns(
        $table,
        array $columns,
        array $data,
        array $dataTypes,
        $returning,
        $pkName
    ) {
        parent::insertMany($table, $columns, $data, $dataTypes, false);
        $insertQuery = $this->getLastQuery();
        $id1 = (int)trim($this->quoteValue(
            Utils::getDataFromStatement($this->query('SELECT LAST_INSERT_ID()'),
            Utils::FETCH_VALUE
        )), "'");
        if ($id1 === 0) {
            throw new DbException(
                'Failed to get IDs of inserted records. LAST_INSERT_ID() returned 0',
                DbException::CODE_RETURNING_FAILED
            );
        }
        $id2 = $id1 + count($data) - 1;
        $pkName = $this->quoteName($pkName);
        $query = DbExpr::create(
            "SELECT {$returning} FROM {$table} WHERE {$pkName} BETWEEN {$id1} AND {$id2} ORDER BY {$pkName}"
        );
        $stmnt = $this->query($query);
        if (!$stmnt->rowCount()) {
            throw new DbException(
                'No data received for $returning request after insert. '
                    . "Insert: {$insertQuery}. Select: {$this->getLastQuery()}",
                DbException::CODE_RETURNING_FAILED
            );
        } else if ($stmnt->rowCount() !== count($data)) {
            throw new DbException(
                "Received amount of records ({$stmnt->rowCount()}) differs from expected (" . count($data) . ')'
                    . '. Insert: ' . $insertQuery . '. Select: ' . $this->getLastQuery(),
                DbException::CODE_RETURNING_FAILED
            );
        }
        return Utils::getDataFromStatement($stmnt, Utils::FETCH_ALL);
    }

    protected function resolveUpdateQueryWithReturningColumns(
        $updateQuery,
        $table,
        $returning
    ) {
        $rowsUpdated = parent::exec($updateQuery);
        if (empty($rowsUpdated)) {
            return [];
        }
        $conditionsAndOptions = preg_replace('%^.*?WHERE\s*(.*)$%is', '$1', $updateQuery);
        $selectQuery = DbExpr::create("SELECT {$returning} FROM {$table} WHERE {$conditionsAndOptions}");
        $stmnt = $this->query($selectQuery, Utils::FETCH_ALL);
        if ($stmnt->rowCount() !== $rowsUpdated) {
            throw new DbException(
                "Received amount of records ({$stmnt->rowCount()}) differs from expected ({$rowsUpdated})"
                    . '. Update: ' . $updateQuery . '. Select: ' . $this->getLastQuery(),
                DbException::CODE_RETURNING_FAILED
            );
        }
        return Utils::getDataFromStatement($stmnt, Utils::FETCH_ALL);
    }

    protected function resolveDeleteQueryWithReturningColumns(
        $query,
        $table,
        $returning
    ) {
        $conditions = preg_replace('%^.*WHERE%i', '', $query);
        $stmnt = $this->query("SELECT {$returning} FROM {$table} WHERE {$conditions}");
        if (!$stmnt->rowCount()) {
            return [];
        }
        $this->exec($query);
        return Utils::getDataFromStatement($stmnt, Utils::FETCH_ALL);
    }


    /**
     * Get table description from DB
     * @param string $table
     * @return array
     */
    public function describeTable($table) {
        // todo: implement describeTable
    }

    /**
     * @return DbExpr
     */
    public function getExpressionToSetDefaultValueForAColumn() {
        return DbExpr::create('DEFAULT');
    }


}