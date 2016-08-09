<?php

namespace PeskyORMTest\TestingAdmin;

use PeskyORMTest\TestingBaseDbTable;

class TestingAdminTableTesting extends TestingBaseDbTable  {

    /**
     * Table Name
     * @return string
     */
    static public function getTableName() {
        return 'admins';
    }

}