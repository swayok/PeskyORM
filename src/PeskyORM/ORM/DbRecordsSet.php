<?php

namespace PeskyORM\ORM;

use PeskyORM\Core\DbExpr;

class DbRecordsSet {

    /**
     * @var DbTable
     */
    protected $table;

    /**
     * @var DbExpr|DbSelect|string
     */
    protected $query;

    /**
     * @param string|DbSelect|DbExpr $query
     * @param string|DbTable|null $table - null: only allowed if $query is instance of  DbSelect
     * @return static
     * @throws \InvalidArgumentException
     */
    static public function create($query, $table = null) {
        return new static($query, $table);
    }

    /**
     * @param string|DbSelect|DbExpr $query
     * @param string|DbTable|null $table - null: only allowed if $query is instance of  DbSelect
     * @throws \InvalidArgumentException
     */
    public function __construct($query, $table = null) {
        if (!is_string($query) && !($query instanceof DbSelect) && !($query instanceof DbExpr)) {
            throw new \InvalidArgumentException('$query argument must be an array or instance of DbSelect class');
        }
        if ($query instanceof DbSelect) {
            $table = $query->getTable();
        } else if (empty($table) || (!is_string($table) && !($table instanceof DbTable))) {
            throw new \InvalidArgumentException('$table argument must be a not empty string or instance of DbTable class');
        } else if (is_string($table)) {
            $table = DbClassesManager::getTableInstance($table);
        }
        $this->query = $query;
        $this->table = $table;
    }


}