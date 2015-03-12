<?php

namespace ORM\TableConfig;

use ORM\DbColumnConfig;
use ORM\DbColumnConfig\EnumColumnConfig;
use ORM\DbColumnConfig\IdColumnConfig;
use ORM\DbRelationConfig;
use ORM\DbTableConfig;

class UserTableConfig extends DbTableConfig {

    protected $name = 'users';

    /** @var DbColumnConfig[] */
    protected $columns = array();

    /** @var DbRelationConfig[] */
    protected $relations = array();

    protected function __construct() {
        $this
            ->addColumn(IdColumnConfig::create())
            ->addColumn(
                DbColumnConfig::create('email', DbColumnConfig::TYPE_EMAIL)
                    ->setIsNullable(false)
                    ->setIsRequired(DbColumnConfig::ON_CREATE)
                    ->setIsUnique(true)
            )->addColumn(
                DbColumnConfig::create('password', DbColumnConfig::TYPE_STRING)
                    ->setIsNullable(false)
                    ->setIsRequired(DbColumnConfig::ON_CREATE)
            )->addColumn(
                DbColumnConfig::create('confirmed', DbColumnConfig::TYPE_BOOL)
                    ->setIsNullable(false)
                    ->setIsRequired(false)
                    ->setDefaultValue(false)
            )->addColumn(
                EnumColumnConfig::create('region', array(
                        '?',
                        'russia',
                        'europe',
                        'usa',
                        'south_america',
                        'canada',
                        'england',
                        'china',
                        'asia',
                        'africa',
                        'australia',
                    ))
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setDefaultValue('?')
            )->addColumn(
                DbColumnConfig::create('created', DbColumnConfig::TYPE_TIMESTAMP)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setIsExcluded(true)
            )->addColumn(
                DbColumnConfig::create('updated', DbColumnConfig::TYPE_TIMESTAMP)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setIsExcluded(true)
            )->addColumn(
                DbColumnConfig::create('storage_total', DbColumnConfig::TYPE_INT)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setDefaultValue(2048)
            )->addColumn(
                DbColumnConfig::create('storage_used', DbColumnConfig::TYPE_INT)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
            )->addColumn(
                EnumColumnConfig::create('created', array(
                        'not_set',
                        'male',
                        'female'
                    ))
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setDefaultValue('not_set')
            )->addColumn(
                DbColumnConfig::create('first_name', DbColumnConfig::TYPE_STRING)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setDefaultValue('')
            )->addColumn(
                DbColumnConfig::create('mid_name', DbColumnConfig::TYPE_STRING)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setDefaultValue('')
            )->addColumn(
                DbColumnConfig::create('last_name', DbColumnConfig::TYPE_STRING)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setDefaultValue('')
            )->addColumn(
                DbColumnConfig::create('nickname', DbColumnConfig::TYPE_TIMESTAMP)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
            )->addColumn(
                DbColumnConfig::create('badge', DbColumnConfig::TYPE_STRING)
                    ->setIsRequired(false)
                    ->setIsNullable(true)
                    ->setMaxLength(100)
            )->addColumn(
                DbColumnConfig::create('signature', DbColumnConfig::TYPE_STRING)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setMaxLength(140)
                    ->setDefaultValue('')
            )->addColumn(
                DbColumnConfig::create('language', DbColumnConfig::TYPE_STRING)
                    ->setIsRequired(DbColumnConfig::ON_CREATE)
                    ->setIsNullable(false)
                    ->setMaxLength(2)
                    ->setDefaultValue('en')
            )->addColumn(
                DbColumnConfig::create('cycle_recording', DbColumnConfig::TYPE_BOOL)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setDefaultValue(false)
            )->addColumn(
                DbColumnConfig::create('insurance_company_alias', DbColumnConfig::TYPE_TIMESTAMP)
                    ->setIsRequired(false)
                    ->setIsNullable(true)
            )->addColumn(
                DbColumnConfig::create('insurance_contract_id', DbColumnConfig::TYPE_TIMESTAMP)
                    ->setIsRequired(false)
                    ->setIsNullable(true)
            )->addColumn(
                DbColumnConfig::create('insurance_contract_surname', DbColumnConfig::TYPE_STRING)
                    ->setIsRequired(false)
                    ->setIsNullable(false)
                    ->setDefaultValue('')
            )->addColumn(
                DbColumnConfig::create('insurance_contract_surname', DbColumnConfig::TYPE_TIMESTAMP)
                    ->setIsRequired(false)
                    ->setIsNullable(true)
            );
    }




}