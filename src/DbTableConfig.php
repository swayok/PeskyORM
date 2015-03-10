<?php

namespace ORM;


abstract class DbTableConfig {

    protected $db = 'default';
    protected $schema = 'public';
    protected $name;

    /** @var DbColumnConfig[] */
    protected $columns = array();

    /** @var DbRelationConfig[] */
    protected $relations = array();

    static private $instances = array();

    protected function __construct() {}

    static public function get() {
        $className = get_called_class();
        if (empty(self::$instances[$className])) {
            self::$instances[$className] = new $className();
        }
        return self::$instances[$className];
    }

    /**
     * @return string
     */
    public function getDb() {
        return $this->db;
    }

    /**
     * @return string
     */
    public function getSchema() {
        return $this->schema;
    }

    /**
     * @return mixed
     */
    public function getName() {
        return $this->name;
    }

    /**
     * @return DbColumnConfig[]
     */
    public function getColumns() {
        return $this->columns;
    }

    /**
     * @return DbRelationConfig[]
     */
    public function getRelations() {
        return $this->relations;
    }


}