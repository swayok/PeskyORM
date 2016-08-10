<?php

namespace PeskyORMTest\TestingAdmin;

use PeskyORMTest\TestingBaseDbTable;

class TestingAdminsTable extends TestingBaseDbTable  {

    /**
     * Table Name
     * @return string
     */
    static public function getTableName() {
        return 'admins';
    }

}