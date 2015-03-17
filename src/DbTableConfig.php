<?php

namespace ORM;


use ORM\Exception\DbTableConfigException;

abstract class DbTableConfig {

    protected $db = 'default';
    protected $schema = 'public';
    protected $name;
    protected $pk;

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

    protected function addColumn(DbColumnConfig $config) {
        if (!empty($this->columns[$config->getName()])) {
            throw new DbTableConfigException($this, "Duplicate config received for column [{$config->getName()}]");
        }
        $config->setDbTableConfig($this);
        $this->columns[$config->getName()] = $config;
        if ($config->isPk()) {
            if (!empty($this->pk)) {
                throw new DbTableConfigException($this, "Table should not have 2 primary keys: [$this->pk] and [{$config->getName()}]");
            }
            $this->pk = $config->getName();
        }
        return $this;
    }

    public function hasColumn($colName) {
        return !empty($this->columns[$colName]);
    }

    protected function addRelation(DbRelationConfig $config, $alias = null) {
        if ($config->getTable() !== $this->getName()) {
            throw new DbTableConfigException($this, "Invalid source table of relation [{$config->getTable()}]. Source table should be [{$this->getName()}]");
        }
        if ($this->hasColumn($config->getColumn())) {
            throw new DbTableConfigException($this, "Table has no column [{$config->getColumn()}] or column not defined yet");
        }
        if (empty($alias)) {
            $alias = $config->getId();
        }
        if (!empty($this->relations[$alias])) {
            throw new DbTableConfigException($this, "Duplicate config received for relation [{$alias} => {$config->getId()}]");
        }
        $this->relations[$alias] = $config;
        $this->columns[$config->getColumn()]->addRelation($config);
        return $this;
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

    /**
     * @return mixed
     */
    public function getPk() {
        return $this->pk;
    }



}