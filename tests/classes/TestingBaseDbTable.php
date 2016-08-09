<?php

namespace PeskyORMTest;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\ORM\DbTable;

abstract class TestingBaseDbTable extends DbTable {

    /**
     * @return DbAdapterInterface
     * @throws \InvalidArgumentException
     */
    static public function getConnection() {
        return TestApp::getDefautConnection();
    }

}