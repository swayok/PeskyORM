<?php

namespace PeskyORM\TableConfig;

use http\Exception\BadMethodCallException;
use PeskyORM\DbColumnConfig;
use PeskyORM\DbTableConfig;

class FakeDbTableConfig extends DbTableConfig {

    protected $name = '___fake_table___';

    static public function getInstance() {
        throw new BadMethodCallException('Use FakeDbTableConfig::create() method');
    }

    /**
     * @param null $connectionAlias
     * @return FakeDbTableConfig
     */
    final static public function create($connectionAlias = null) {
        /** @var FakeDbTableConfig|DbTableConfig $instance */
        $instance = new static();
        $instance->connectionAlias = $connectionAlias;
        return $instance;
    }

    public function setConnectionAlias($connectionAlias) {
        $this->connectionAlias = $connectionAlias;
        return $this;
    }

    /** @noinspection MagicMethodsValidityInspection */
    /** @noinspection PhpMissingParentConstructorInspection */
    protected function __construct() {
        $this->loadColumnsConfigs();
    }

    protected function loadColumnsConfigs() {
        $this->addColumn(
            DbColumnConfig::create(DbColumnConfig::TYPE_INT, 'id')
                ->setIsPk(true)
                ->setIsNullable(false)
        );
    }

}