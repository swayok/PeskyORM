<?php

namespace PeskyORM\Adapter;

use PeskyORM\Core\DbAdapter;

class Mysql extends DbAdapter {

    const VALUE_QUOTES = '"';
    const NAME_QUOTES = '`';

    protected function makePdo() {
        return new \PDO(
            'mysql:host=' . $this->getDbHost() . ';dbname=' . $this->getDbName(),
            $this->getDbUser(),
            $this->getDbPassword()
        );
    }
}