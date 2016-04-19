<?php

class PostgresAdapterTest extends \PHPUnit_Framework_TestCase {

    protected $globalConfigs = [];

    protected function setUp() {
        $this->globalConfigs = include __DIR__ . '/configs/global.php';
    }

    public function testConstructor() {

    }
}
