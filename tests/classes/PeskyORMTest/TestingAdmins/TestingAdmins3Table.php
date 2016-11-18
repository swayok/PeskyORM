<?php

namespace PeskyORMTest\TestingAdmins;

use PeskyORMTest\TestingBaseTable;

class TestingAdmins3Table extends TestingBaseTable  {

    public function getTableStructure() {
        return TestingAdmins3TableStructure::getInstance();
    }


}