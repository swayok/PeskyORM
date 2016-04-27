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

    public function isDbSupportsTableSchemas() {
        return false;
    }

    public function getDefaultTableSchema() {
        return null;
    }

    public function __construct(MysqlConfig $connectionConfig) {
        parent::__construct($connectionConfig);
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
                'Received amount of records (' . count($data) . ") differs from expected ({$stmnt->rowCount()})"
                    . '. Insert: ' . $insertQuery . '. Select: ' . $this->getLastQuery(),
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
        if ($stmnt->rowCount()) {
            $this->exec($query);
            return Utils::getDataFromStatement($stmnt, Utils::FETCH_ALL);
        }
        return [];
    }


}