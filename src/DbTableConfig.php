<?php

namespace ORM;


use ORM\Exception\DbTableConfigException;

abstract class DbTableConfig {

    protected $db = 'default';
    protected $schema = 'public';
    protected $name;
    protected $pk = null;
    protected $hasFileColumns = false;
    protected $fileColumns = array();

    /** @var DbColumnConfig[] */
    protected $columns = array();

    /** @var DbRelationConfig[] */
    protected $relations = array();

    /** @var DbTableConfig[]  */
    static private $instances = array();

    protected function __construct() {}

    /**
     * @return $this
     */
    static public function get() {
        $className = get_called_class();
        if (empty(self::$instances[$className])) {
            self::$instances[$className] = new $className();
        }
        return self::$instances[$className];
    }

    /**
     * @param DbColumnConfig $config
     * @return $this
     * @throws DbTableConfigException
     */
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
        if ($config->isFile()) {
            $this->fileColumns[$config->getName()] = $config;
            $this->hasFileColumns = true;
        }
        return $this;
    }

    /**
     * @param string $colName
     * @return bool
     */
    public function hasColumn($colName) {
        return is_string($colName) && !empty($this->columns[$colName]);
    }

    /**
     * @param string $colName
     * @return DbColumnConfig
     * @throws DbTableConfigException
     */
    public function getColumn($colName) {
        if (!$this->hasColumn($colName)) {
            throw new DbTableConfigException($this, "Table does not contain column [{$colName}]");
        }
        return $this->columns[$colName];
    }

    /**
     * @param DbRelationConfig $config
     * @param null $alias
     * @return $this
     * @throws DbTableConfigException
     * @throws Exception\DbColumnConfigException
     */
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
     * @param string $alias
     * @return bool
     */
    public function hasRelation($alias) {
        return is_string($alias) && !empty($this->relations[$alias]);
    }

    /**
     * @param string $alias
     * @return DbRelationConfig
     * @throws DbTableConfigException
     */
    public function getRelation($alias) {
        if (!$this->hasRelation($alias)) {
            throw new DbTableConfigException($this, "Table has no relation [{$alias}]");
        }
        return $this->relations[$alias];
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
     * @return string
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
     * @return string|null
     */
    public function getPk() {
        return $this->pk;
    }

    /**
     * @return bool
     */
    public function hasPk() {
        return $this->pk !== null;
    }

    /**
     * @return bool
     */
    public function hasFileColumns() {
        return $this->hasFileColumns;
    }

    /**
     * @param $colName
     * @return bool
     */
    public function hasFileColumn($colName) {
        return is_string($colName) && !empty($this->hasFileColumns[$colName]);
    }

    /**
     * @return DbColumnConfig[] = array('column_name' => DbColumnConfig)
     */
    public function getFileColumns() {
        return $this->fileColumns;
    }

}