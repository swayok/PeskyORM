<?php

namespace PeskyORM\TableConfig;

use PeskyORM\DbColumnConfig;
use PeskyORM\DbColumnConfig\EnumColumnConfig;
use PeskyORM\DbColumnConfig\PkColumnConfig;
use PeskyORM\DbRelationConfig;
use PeskyORM\DbTableConfig;

class UserTokenTableConfig extends DbTableConfig {

    protected $name = 'user_tokens';

    /** @var DbColumnConfig[] */
    protected $columns = array();

    /** @var DbRelationConfig[] */
    protected $relations = array();

    protected function __construct() {
        $this
            ->addColumn(
                PkColumnConfig::create('token', DbColumnConfig::TYPE_SHA1)
                    ->setIsRequired(DbColumnConfig::ON_ALL)
            )
            ->addColumn(
                DbColumnConfig::create('user_id', DbColumnConfig::TYPE_INT)
                    ->setIsNullable(true)
                    ->setIsRequired(DbColumnConfig::ON_ALL)
            )->addColumn(
                DbColumnConfig::create('user_agent', DbColumnConfig::TYPE_STRING)
                    ->setIsNullable(false)
                    ->setIsRequired(DbColumnConfig::ON_ALL)
                    ->setMaxLength(500)
            )->addColumn(
                DbColumnConfig::create('remember', DbColumnConfig::TYPE_BOOL)
                    ->setIsNullable(false)
                    ->setIsRequired(false)
                    ->setDefaultValue(false)
            )->addColumn(
                DbColumnConfig::create('created', DbColumnConfig::TYPE_TIMESTAMP)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setIsExcluded(true)
            );

        $this->addRelation(
            new DbRelationConfig(
                $this,
                'user_id',
                DbRelationConfig::BELONGS_TO,
                'users',
                'id'
            ),
            'User'
        );
    }




}