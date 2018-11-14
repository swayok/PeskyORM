<?php

namespace PeskyORM;

use http\Exception\BadMethodCallException;
use PeskyORM\TableConfig\FakeDbTableConfig;

class FakeDbModel extends DbModel {

    protected $configClass = FakeDbTableConfig::class;
    static protected $objectClass = FakeDbObject::class;
    protected $connectionAlias;
    protected $alias = 'FakeTable';

    public static function getInstance() {
        throw new BadMethodCallException('Use FakeDbModel::create() method');
    }

    static public function create($connectionAlias) {
        /** @var FakeDbModel $instance */
        $instance = new static();
        $instance->connectionAlias = $connectionAlias;
        $instance->getTableConfig()->setConnectionAlias($connectionAlias);
        return $instance;
    }

    /** @noinspection MagicMethodsValidityInspection */
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct() {
        $this->namespace = __NAMESPACE__;
        /** @var FakeDbTableConfig $configClass */
        $configClass = $this->configClass;
        $this->tableConfig = $configClass::create();
        $this->orderField = $this->tableConfig->getPk();
    }

    public function getConnectionAlias() {
        return $this->connectionAlias;
    }

    /**
     * @return FakeDbTableConfig
     */
    public function getTableConfig() {
        return $this->tableConfig;
    }

    public static function getOwnDbObject($data = null, $filter = false, $isDbValues = false) {
        $model = static::create('fake');
        $dbObjectClass = static::$objectClass;
        return new $dbObjectClass($data, $filter, $isDbValues, $model);
    }

    public static function getFullDbObjectClass($dbObjectNameOrTableName) {
        return parent::getFullDbObjectClass($dbObjectNameOrTableName);
    }
}