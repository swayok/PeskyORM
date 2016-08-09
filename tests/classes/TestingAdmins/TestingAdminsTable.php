<?php

namespace PeskyORMTest\TestingAdmin;

use PeskyORMTest\TestingBaseDbTable;

class TestingAdminsTableTesting extends TestingBaseDbTable  {

    /**
     * Table Name
     * @return string
     */
    static public function getTableName() {
        return 'admins';
    }

}