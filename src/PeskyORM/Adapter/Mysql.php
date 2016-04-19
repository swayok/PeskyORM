<?php

namespace PeskyORM\Adapter;

use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Core\DbAdapter;

class Mysql extends DbAdapter {

    const VALUE_QUOTES = '"';
    const NAME_QUOTES = '`';

    public function __construct(MysqlConfig $connectionConfig) {
        parent::__construct($connectionConfig);
    }


}