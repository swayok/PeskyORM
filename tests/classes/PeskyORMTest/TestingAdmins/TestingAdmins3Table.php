<?php

namespace PeskyORMTest\TestingAdmins;

use PeskyORMTest\TestingBaseDbTable;

class TestingAdmins3Table extends TestingBaseDbTable  {

    public function getTableStructure() {
        return TestingAdmins3TableStructure::getInstance();
    }


}