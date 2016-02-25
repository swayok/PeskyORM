<?php

namespace PeskyORM\Adapter;

use PeskyORM\DbAdapter;

class Mysql extends DbAdapter{

    const VALUE_QUOTES = '"';
    const NAME_QUOTES = "`";

    protected function makePdo() {
        return new \PDO(
            'mysql:host=' . $this->dbHost . (!empty($this->dbName) ? ';dbname=' . $this->dbName : ''),
            $this->dbUser,
            $this->dbPassword
        );
    }
}