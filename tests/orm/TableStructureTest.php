<?php

use PeskyORMTest\TestingApp;

class TableStructureTest extends \PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        TestingApp::init();
    }

//    static public function tearDownAfterClass() {
//        TestingApp::clearTables();
//    }
//
//    protected function tearDown() {
//        TestingApp::clearTables();
//    }

    public function testTableStructure() {
        $structure = \PeskyORMTest\TestingAdmin\TestingAdminsTableStructure::i();
    }
}
