<?php

require_once __DIR__ . '/PostgresAdapterSelectDataTest.php';

use PeskyORMTest\TestingApp;

class MysqlAdapterSelectDataTest extends PostgresAdapterSelectDataTest {

    static protected function getValidAdapter() {
        return TestingApp::getMysqlConnection();
    }

    public function convertTestDataForAdminsTableAssert($data) {
        foreach ($data as &$item) {
            $item['id'] = "{$item['id']}";
            $item['is_superadmin'] = $item['is_superadmin'] ? '1' : '0';
            $item['is_active'] = $item['is_active'] ? '1' : '0';
            $item['created_at'] = preg_replace('%\+00$%', '', $item['created_at']);
            $item['updated_at'] = preg_replace('%\+00$%', '', $item['updated_at']);
            $item['not_changeable_column'] = 'not changable';
        }
        return $data;
    }
}
