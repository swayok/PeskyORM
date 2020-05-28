<?php

require_once __DIR__ . '/PostgresAdapterDeleteDataTest.php';

class MysqlAdapterDeleteTest extends PostgresAdapterDeleteTest {

    /**
     * @return \PeskyORM\Adapter\Mysql
     */
    static protected function getValidAdapter() {
        return \PeskyORMTest\TestingApp::getMysqlConnection();
    }

}
