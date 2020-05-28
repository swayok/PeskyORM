<?php

namespace Tests\PeskyORMTest\TestingAdmins;

use Tests\PeskyORMTest\TestingBaseTable;

class TestingAdmins3Table extends TestingBaseTable  {

    public function getTableStructure() {
        return TestingAdmins3TableStructure::getInstance();
    }


}