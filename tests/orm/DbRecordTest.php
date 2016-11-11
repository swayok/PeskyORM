<?php

use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\DbRecord;
use PeskyORM\ORM\DbRecordValue;
use PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORMTest\TestingApp;
use PeskyORMTest\TestingSettings\TestingSetting;
use PeskyORMTest\TestingSettings\TestingSettingsTable;
use PeskyORMTest\TestingSettings\TestingSettingsTableStructure;
use Swayok\Utils\NormalizeValue;

class DbRecordTest extends PHPUnit_Framework_TestCase {

    public static function setUpBeforeClass() {
        TestingApp::init();
        TestingApp::cleanInstancesOfDbTablesAndStructures();
    }

    public static function tearDownAfterClass() {
        TestingApp::clearTables();
        TestingApp::cleanInstancesOfDbTablesAndStructures();
    }

    protected function setUp() {
        TestingApp::cleanInstancesOfDbTablesAndStructures();
    }

    private function insertMinimalTestDataToAdminsTable() {
        $data = [
            [
                'id' => 1,
                'login' => '2AE351AF-131D-6654-9DB2-79B8F273986C',
                'password' => password_hash('KIS37QEG4HT', PASSWORD_DEFAULT),
                'parent_id' => null,
                'created_at' => '2015-05-14 02:12:05+00',
                'updated_at' => '2015-06-10 19:30:24+00',
                'remember_token' => '6A758CB2-234F-F7A1-24FE-4FE263E6FF81',
                'is_superadmin' => true,
                'language' => 'en',
                'ip' => '192.168.0.1',
                'role' => 'admin',
                'is_active' => 1,
                'name' => 'Lionel Freeman',
                'email' => 'diam.at.pretium@idmollisnec.co.uk',
                'timezone' => 'Europe/Moscow'
            ],
            [
                'id' => 2,
                'login' => 'ADCE237A-9E48-BECD-1F01-1CACA964CF0F',
                'password' => password_hash('NKJ63NMV6NY', PASSWORD_DEFAULT),
                'parent_id' => 1,
                'created_at' => '2015-05-14 06:54:01+00',
                'updated_at' => '2015-05-19 23:48:17+00',
                'remember_token' => '0A2E7DA9-6072-34E2-38E8-2675C73F3419',
                'is_superadmin' => true,
                'language' => 'en',
                'ip' => '192.168.0.1',
                'role' => 'admin',
                'is_active' => false,
                'name' => 'Jasper Waller',
                'email' => 'elit@eratvelpede.org',
                'timezone' => 'Europe/Moscow'
            ]
        ];
        TestingApp::$dbConnection->insertMany('admins', array_keys($data[0]), $data);
    }

    private function getDataForSingleAdmin($withId = false) {
        return array_merge($withId ? ['id' => 1] : [], [
            'login' => '2AE351AF-131D-6654-9DB2-79B8F273986C',
            'parent_id' => 1,
            'created_at' => '2015-05-14 02:12:05',
            'updated_at' => '2015-06-10 19:30:24',
            'remember_token' => '6A758CB2-234F-F7A1-24FE-4FE263E6FF81',
            'is_superadmin' => true,
            'language' => 'en',
            'ip' => '192.168.0.1',
            'role' => 'admin',
            'is_active' => '1',
            'name' => 'Lionel Freeman',
            'email' => 'diam.at.pretium@idmollisnec.co.uk',
            'timezone' => 'Europe/Moscow'
        ]);
    }

    private function normalizeAdmin($adminData, $addNotChangeableCol = true, $addNotExistingCol = true) {
        $adminData['is_superadmin'] = NormalizeValue::normalizeBoolean($adminData['is_superadmin']);
        $adminData['is_active'] = NormalizeValue::normalizeBoolean($adminData['is_active']);
        $adminData['password'] = null;
        if ($adminData['parent_id'] !== null) {
            $adminData['parent_id'] = NormalizeValue::normalizeInteger($adminData['parent_id']);
        }
        if (array_key_exists('id', $adminData)) {
            $adminData['id'] = NormalizeValue::normalizeInteger($adminData['id']);
        } else {
            $adminData['id'] = null;
        }
        if ($addNotChangeableCol || $addNotChangeableCol === null) {
            $adminData['not_changeable_column'] = $addNotChangeableCol === null ? null : 'not changable';
        }
        if ($addNotExistingCol) {
            $adminData['not_existing_column'] = null;
        }
        return $adminData;
    }

    /**
     * @param DbRecord $object
     * @param string $propertyName
     * @return mixed
     */
    private function getObjectPropertyValue($object, $propertyName) {
        $reflection = new ReflectionClass($object);
        $prop = $reflection->getProperty($propertyName);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    /**
     * @param DbRecord $object
     * @param string $methodName
     * @param array $args
     * @return mixed
     * @internal param string $propertyName
     */
    private function callObjectMethod($object, $methodName, ...$args) {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $args);
    }

    public function testConstructor() {
        $rec1 = new TestingAdmin();
        static::assertFalse($rec1->existsInDb());
        static::assertFalse($rec1->hasValue('id', false));
        static::assertFalse($rec1->hasValue('parent_id', false));

        $rec2 = TestingAdmin::newEmptyRecord();
        static::assertInstanceOf(TestingAdmin::class, $rec2);
        static::assertFalse($rec2->existsInDb());
        static::assertFalse($rec2->hasValue('id', false));
        static::assertFalse($rec2->hasValue('parent_id', false));

        $rec3 = TestingAdmin::_();
        static::assertInstanceOf(TestingAdmin::class, $rec3);
        static::assertFalse($rec3->existsInDb());
        static::assertFalse($rec3->hasValue('id', false));
        static::assertFalse($rec3->hasValue('parent_id', false));
    }

    public function testReset() {
        $rec = TestingAdmin::newEmptyRecord()
            ->setValue('id', 1, true)
            ->setValue('parent_id', 2, true);
        static::assertTrue($rec->hasValue('id', false));
        static::assertTrue($rec->hasValue('parent_id', false));
        $rec->next();
        static::assertEquals(1, $this->getObjectPropertyValue($rec, 'iteratorIdx'));
        $rec->updateValues(['Parent' => ['id' => 2, 'parent_id' => null]], true);
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'relatedRecords'));
        /** @var DbRecordValue $valId1 */
        $valId1 = $this->callObjectMethod($rec, 'getValueObject', 'id');
        static::assertFalse($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        $rec->begin()->setValue('parent_id', 3, false);
        static::assertTrue($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        $rec->rollback();
        $rec->reset();
        static::assertFalse($rec->hasValue('id', false));
        static::assertFalse($rec->hasValue('parent_id', false));
        static::assertEquals(1, $valId1->getValue());
        static::assertEquals(0, $this->getObjectPropertyValue($rec, 'iteratorIdx'));
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'relatedRecords'));
        static::assertFalse($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        /** @var DbRecordValue $valId2 */
        $valId2 = $this->callObjectMethod($rec, 'getValueObject', 'id');
        static::assertFalse($valId2->hasOldValue());
        $rec->setValue('id', 2, true);
        /** @var DbRecordValue $valId3 */
        $valId3 = $this->callObjectMethod($rec, 'getValueObject', 'id');
        static::assertEquals(2, $valId3->getValue());
    }

    public function testStaticMethods() {
        // tables and structures
        static::assertInstanceOf(TestingSetting::class, TestingSetting::_());
        static::assertInstanceOf(TestingSetting::class, TestingSetting::newEmptyRecord());
        static::assertInstanceOf(TestingSettingsTable::class, TestingSetting::getTable());
        static::assertInstanceOf(TestingSettingsTableStructure::class, TestingSetting::getTableStructure());

        static::assertInstanceOf(TestingAdmin::class, TestingAdmin::_());
        static::assertInstanceOf(TestingAdmin::class, TestingAdmin::newEmptyRecord());
        static::assertInstanceOf(TestingAdminsTable::class, TestingAdmin::getTable());
        static::assertInstanceOf(TestingAdminsTableStructure::class, TestingAdmin::getTableStructure());

        // columns
        static::assertEquals(TestingAdminsTableStructure::getColumns(), TestingAdmin::getColumns());
        static::assertTrue(TestingAdmin::hasColumn('language'));
        static::assertEquals(
            TestingAdminsTableStructure::getColumn('language'),
            TestingAdmin::getColumn('language')
        );
        static::assertEquals(
            TestingAdminsTableStructure::getColumn('parent_id'),
            TestingAdmin::getColumn('parent_id')
        );
        static::assertEquals(
            TestingAdminsTableStructure::getColumn('id'),
            TestingAdmin::getPrimaryKeyColumn()
        );

        static::assertEquals(TestingSettingsTableStructure::getColumns(), TestingSetting::getColumns());
        static::assertFalse(TestingSetting::hasColumn('language'));
        static::assertTrue(TestingSetting::hasColumn('key'));
        static::assertEquals(
            TestingSettingsTableStructure::getColumn('key'),
            TestingSetting::getColumn('key')
        );
        static::assertEquals(
            TestingSettingsTableStructure::getColumn('value'),
            TestingSetting::getColumn('value')
        );
        static::assertEquals('id', TestingSetting::getPrimaryKeyColumnName());

        // relations
        static::assertEquals(TestingAdminsTableStructure::getRelations(), TestingAdmin::getRelations());
        static::assertTrue(TestingAdmin::hasRelation('Parent'));
        static::assertEquals(
            TestingAdminsTableStructure::getRelation('Parent'),
            TestingAdmin::getRelation('Parent')
        );

        static::assertEquals(TestingSettingsTableStructure::getRelations(), TestingSetting::getRelations());
        static::assertFalse(TestingSetting::hasRelation('Parent'));

        // file columns
        static::assertTrue(TestingAdmin::hasFileColumns());
        static::assertEquals(TestingAdminsTableStructure::getFileColumns(), TestingAdmin::getFileColumns());

        static::assertFalse(TestingSetting::hasFileColumns());
        static::assertEquals(TestingSettingsTableStructure::getFileColumns(), TestingSetting::getFileColumns());

        // validate value
        static::assertEquals([], TestingAdmin::validateValue('language', 'ru', true));
        static::assertEquals([], TestingAdmin::validateValue('language', 'ru', false));
        static::assertEquals(['Value is not allowed'], TestingAdmin::validateValue('language', 'qq', true));
        static::assertEquals(['Value is not allowed'], TestingAdmin::validateValue('language', 'qq', false));

        // columns that exist in db or not
        static::assertEquals(['avatar', 'some_file', 'not_existing_column'], array_keys(TestingAdmin::getColumnsThatDoNotExistInDb()));
        static::assertEquals([], array_keys(TestingSetting::getColumnsThatDoNotExistInDb()));
        static::assertEquals(
            [
                'id', 'parent_id', 'login', 'password', 'created_at', 'updated_at', 'remember_token',
                'is_superadmin', 'language', 'ip', 'role', 'is_active', 'name', 'email', 'timezone',
                'not_changeable_column'
            ],
            array_keys(TestingAdmin::getColumnsThatExistInDb())
        );
        static::assertEquals(['id', 'key', 'value'], array_keys(TestingSetting::getColumnsThatExistInDb()));
    }

    public function testCreateDbValueObject() {
        $rec = TestingAdmin::newEmptyRecord();
        $this->callObjectMethod($rec, 'createValueObject', $rec::getColumn('id'));
        static::assertInstanceOf(DbRecordValue::class, $this->callObjectMethod($rec, 'getValueObject', 'id'));
        static::assertInstanceOf(DbRecordValue::class, $this->callObjectMethod($rec, 'getValueObject', $rec::getColumn('id')));
    }

    public function testResetValue() {
        $rec = TestingAdmin::newEmptyRecord();
        $rec->setValue('id', 1, true);
        static::assertEquals(1, $rec->getValue('id'));
        $this->callObjectMethod($rec, 'resetValue', 'id');
        static::assertFalse($rec->hasValue('id', false));
        $rec->setValue('id', 1, true);
        static::assertEquals(1, $rec->getValue('id'));
        $this->callObjectMethod($rec, 'resetValue', $rec::getColumn('id'));
        static::assertFalse($rec->hasValue('id', false));
    }

    public function testCleanUpdates() {
        $rec = TestingAdmin::newEmptyRecord();
        $rec->setValue('id', 1, true)->begin()->setValue('parent_id', 2, false);
        static::assertTrue($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        $this->callObjectMethod($rec, 'cleanUpdates');
        static::assertFalse($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
    }

    public function testGetValueObject() {
        $rec = TestingAdmin::newEmptyRecord();
        static::assertEquals(
            $this->getObjectPropertyValue($rec, 'values')['id'],
            $this->callObjectMethod($rec, 'getValueObject', 'id')
        );
        static::assertEquals(
            $this->getObjectPropertyValue($rec, 'values')['parent_id'],
            $this->callObjectMethod($rec, 'getValueObject', $rec::getColumn('parent_id'))
        );
    }

    public function testGetValue() {
        $rec = TestingAdmin::newEmptyRecord();
        static::assertEquals(TestingAdmin::getColumn('id')->getDefaultValueAsIs(), $rec->getValue('id'));
        $rec->setValue('id', 2, true);
        static::assertEquals(2, $rec->getValue('id'));
        static::assertEquals(2, $rec->getValue(TestingAdmin::getColumn('id')));
    }

    public function testHasValueOrDefaultValue() {
        $rec = new TestingAdmin();
        static::assertFalse($rec->hasValue('id'));
        static::assertFalse($rec->hasValue('id', false));
        static::assertTrue($rec->hasValue('id', true));

        $val = $this->callObjectMethod($rec, 'getValueObject', 'id');
        static::assertFalse($this->callObjectMethod($rec, '_hasValue', $val));
        static::assertFalse($this->callObjectMethod($rec, '_hasValue', $val, false));
        static::assertTrue($this->callObjectMethod($rec, '_hasValue', $val, true));

        $rec->setValue('id', 2, true);
        static::assertTrue($rec->hasValue('id', false));
        static::assertTrue($rec->hasValue('id', true));

        static::assertFalse($rec->hasValue('parent_id'));
        static::assertFalse($rec->hasValue('parent_id', false));
        static::assertFalse($rec->hasValue('parent_id', true));
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage It is forbidden to modify or set value of a 'not_changeable_column' column
     */
    public function testInvalidSetValue1() {
        $rec = new TestingAdmin();
        $rec->setValue('not_changeable_column', 1, false);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Attempt to set a value for column [parent_id] with flag $isFromDb === true while record does not exist in DB
     */
    public function testInvalidSetValue2() {
        $rec = new TestingAdmin();
        $rec->setValue('parent_id', 1, true);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Attempt to set a value for column [parent_id] with flag $isFromDb === true while record does not exist in DB
     */
    public function testInvalidSetValue3() {
        $rec = new TestingAdmin();
        $rec->setValue('parent_id', 1, true);
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage It is forbidden to set value with $isFromDb === true after begin()
     */
    public function testInvalidSetValue4() {
        $rec = new TestingAdmin();
        $rec->setValue('id', 1, true);
        $rec->begin()->setValue('parent_id', 2, true);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage It is forbidden to set primary key value when $isFromDb === false
     */
    public function testInvalidSetPkValue() {
        $rec = new TestingAdmin();
        $rec->setValue('id', 1, false);
    }

    public function testSetValueAndPkValue() {
        $rec = new TestingAdmin();
        static::assertFalse($rec->hasValue('id'));
        $rec->setValue('id', 2, true);
        static::assertTrue($rec->hasValue('id'));
        static::assertEquals(2, $rec->getValue('id'));
        /** @var DbRecordValue $val */
        $val = $this->callObjectMethod($rec, 'getValueObject', 'id');
        static::assertTrue($val->isItFromDb());
        static::assertFalse($val->hasOldValue());
        $rec->setValue('id', 3, true);
        static::assertTrue($rec->hasValue('id'));
        static::assertEquals(3, $rec->getValue('id'));
        /** @var DbRecordValue $val */
        $val = $this->callObjectMethod($rec, 'getValueObject', 'id');
        static::assertTrue($val->isItFromDb());
        static::assertTrue($val->hasOldValue());
        static::assertEquals(2, $val->getOldValue());

        $rec->setValue('id', 4, true)->begin()->setValue('parent_id', 3, false);
        static::assertTrue($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('parent_id', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertNotEquals(
            $this->getObjectPropertyValue($rec, 'valuesBackup')['parent_id'],
            $this->callObjectMethod($rec, 'getValueObject', 'parent_id')
        );
        $rec->rollback();

        static::assertTrue($rec->hasPrimaryKeyValue());
        static::assertEquals(4, $rec->getPrimaryKeyValue());
        $rec->reset();
        static::assertFalse($rec->hasPrimaryKeyValue());

        $rec
            ->reset()
            ->setValue('id', 1, true)
            ->setValue('parent_id', 4, true)
            ->setValue('email', 'test@test.cc', true);
        static::assertTrue($rec->hasPrimaryKeyValue());
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertTrue($rec->isValueFromDb('email'));
        $rec->setValue('id', 2, true);
        static::assertTrue($rec->hasPrimaryKeyValue());
        static::assertEquals(2, $rec->getValue('id'));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertEquals(4, $rec->getValue('parent_id'));
        static::assertFalse($rec->isValueFromDb('parent_id'));
        static::assertEquals('test@test.cc', $rec->getValue('email'));
        static::assertFalse($rec->isValueFromDb('email'));

        $rec
            ->reset()
            ->setValue('id', 1, true)
            ->setValue('parent_id', 4, true)
            ->setValue('email', 'test@test.cc', true);
        $rec->unsetPrimaryKeyValue();
        static::assertFalse($rec->hasPrimaryKeyValue());
        static::assertEquals(4, $rec->getValue('parent_id'));
        static::assertFalse($rec->isValueFromDb('parent_id'));
        static::assertEquals('test@test.cc', $rec->getValue('email'));
        static::assertFalse($rec->isValueFromDb('email'));
    }

    public function testExistsInDb() {
        $this->insertMinimalTestDataToAdminsTable();

        $rec = new TestingAdmin();
        $prevQuery = TestingAdminsTable::getLastQuery();
        static::assertFalse($rec->existsInDb());
        static::assertFalse($rec->existsInDb(true));
        static::assertEquals($prevQuery, TestingAdminsTable::getLastQuery());

        $rec->setValue('id', 1, true);
        $prevQuery = TestingAdminsTable::getLastQuery();
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertNotEquals($prevQuery, TestingAdminsTable::getLastQuery());

        $rec->setValue('id', 888, true);
        $prevQuery = TestingAdminsTable::getLastQuery();
        static::assertTrue($rec->existsInDb());
        static::assertFalse($rec->existsInDb(true));
        static::assertNotEquals($prevQuery, TestingAdminsTable::getLastQuery());
    }

    public function testGetDefaults() {
        $rec = TestingAdmin::newEmptyRecord();
        static::assertEquals(
            [
                'id' => null,
                'parent_id' => null,
                'login' => null,
                'password' => null,
                'created_at' => null,
                'updated_at' => null,
                'remember_token' => null,
                'is_superadmin' => false,
                'language' => 'en',
                'ip' => null,
                'role' => 'guest',
                'is_active' => true,
                'name' => '',
                'email' => null,
                'timezone' => 'UTC',
                'not_changeable_column' => null
            ],
            $rec->getDefaults()
        );
        static::assertEquals(
            [
                'id' => TestingAdminsTableStructure::getColumn('id')->getDefaultValueAsIs(),
                'parent_id' => null,
                'login' => null,
                'password' => null,
                'created_at' => TestingAdminsTableStructure::getColumn('created_at')->getDefaultValueAsIs(),
                'updated_at' => null,
                'remember_token' => null,
                'is_superadmin' => false,
                'language' => 'en',
                'ip' => null,
                'role' => 'guest',
                'is_active' => true,
                'name' => '',
                'email' => null,
                'timezone' => 'UTC',
                'avatar' => null,
                'some_file' => null,
                'not_changeable_column' => null,
                'not_existing_column' => null,
            ],
            $rec->getDefaults([], false, false)
        );
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Related record with name 'Parent' is not set and autoloading is disabled
     */
    public function testInvalidRelationRequestInToArray1() {
        TestingAdmin::fromArray(['id' => 1], true)->toArrayWithoutFiles(['id'], ['Parent']);
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Related record with name 'Children' is not set and autoloading is disabled
     */
    public function testInvalidRelationRequestInToArray2() {
        TestingAdmin::fromArray(['id' => 1], true)->toArrayWithoutFiles(['id'], ['Children']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Table has no relation named 'Invalid'
     */
    public function testInvalidRelationRequestInToArray3() {
        TestingAdmin::fromArray(['id' => 1], true)->toArrayWithoutFiles(['id'], ['Invalid']);
    }

    /**
     * @covers DbRecord::getColumnValueForToArray()
     */
    public function testGetColumnValueForToArray() {
        $rec = TestingAdmin::fromArray(['parent_id' => 1], false);
        $reflection = new ReflectionClass($rec);
        $method = $reflection->getMethod('getColumnValueForToArray');
        $method->setAccessible(true);
        static::assertEquals(null, $method->invoke($rec, 'id', false));
        static::assertEquals(1, $method->invoke($rec, 'parent_id', false));
        static::assertEquals('en', $method->invoke($rec, 'language', false));
        static::assertEquals(null, $method->invoke($rec, 'avatar', false));
        static::assertEquals(null, $method->invoke($rec, 'avatar', true));
        $rec->setValue('id', 2, true);
        static::assertEquals(2, $method->invoke($rec, 'id', false));
        static::assertEquals(1, $method->invoke($rec, 'parent_id', false));
        static::assertEquals(null, $method->invoke($rec, 'language', false));
        static::assertEquals(null, $method->invoke($rec, 'avatar', false));
        static::assertEquals(null, $method->invoke($rec, 'avatar', true));
        $method->setAccessible(false);
    }

    /**
     * @covers DbRecord::toArray()
     * @covers DbRecord::toArrayWithoutFiles()
     */
    public function testToArray() {
        // toArray, toArrayWithoutFiles
        $rec = TestingAdmin::fromArray([]);
        static::assertEquals(
            [
                'id' => null,
                'parent_id' => null,
                'login' => null,
                'password' => null,
                'created_at' => null,
                'updated_at' => null,
                'remember_token' => null,
                'is_superadmin' => false,
                'language' => 'en',
                'ip' => null,
                'role' => 'guest',
                'is_active' => true,
                'name' => '',
                'email' => null,
                'timezone' => 'UTC',
                'not_changeable_column' => null,
                'not_existing_column' => null
            ],
            $rec->toArrayWithoutFiles()
        );

        $admin = $this->getDataForSingleAdmin(true);
        $adminNormalized = $this->normalizeAdmin($admin, null);
        $toArray = $rec->fromData($admin, true)->toArray();
        $toArrayPartial = $rec->toArray(['id', 'parent_id', 'login', 'role']);
        static::assertEquals(
            array_diff_key($adminNormalized, array_flip(['not_changeable_column', 'not_existing_column', 'password'])),
            $toArray
        );
        static::assertEquals(array_intersect_key($adminNormalized, $toArrayPartial), $toArrayPartial);

        $adminNoId = $this->getDataForSingleAdmin(false);
        $adminNoIdNormalized = $this->normalizeAdmin($adminNoId, null);
        $toArray = $rec->fromData($adminNoId)->toArrayWithoutFiles();
        $toArrayPartial = $rec->toArrayWithoutFiles(['id', 'parent_id', 'login', 'role']);
        static::assertEquals(array_merge(['id' => null], $adminNoIdNormalized), $toArray);
        static::assertEquals(array_intersect_key(array_merge(['id' => null], $adminNoIdNormalized), $toArrayPartial), $toArrayPartial);

        $adminNoId['Parent'] = $adminNoId;
        $toArrayRelation = $rec->fromData($adminNoId)->toArrayWithoutFiles(['id'], ['Parent']);
        static::assertEquals(
            ['id' => null, 'Parent' => array_merge(['id' => null], $adminNoIdNormalized)],
            $toArrayRelation
        );

        $insertedRecords = TestingApp::fillAdminsTable(10);
        unset($adminNoId['Parent']);
        $toArrayRelation = $rec->read($insertedRecords[1]['id'])->toArrayWithoutFiles(['id'], ['Parent'], true);
        static::assertEquals(
            ['id' => $insertedRecords[1]['id'], 'Parent' => $insertedRecords[0]],
            array_diff_key($toArrayRelation, ['not_existing_column' => ''])
        );

        $toArrayRelation = $rec->read($insertedRecords[1]['id'])->toArrayWithoutFiles(['id'], ['Parent' => ['login']], true);
        static::assertEquals(
            ['id' => $insertedRecords[1]['id'], 'Parent' => ['login' => $insertedRecords[0]['login']]],
            $toArrayRelation
        );

        $toArrayRelation = $rec->read($insertedRecords[0]['id'])->toArrayWithoutFiles(['id'], ['Children'], true);
        static::assertEquals(
            ['id' => $insertedRecords[0]['id'], 'Children' => [$insertedRecords[1], $insertedRecords[2]]],
            $toArrayRelation
        );

        $toArrayRelation = $rec->read($insertedRecords[0]['id'])->toArrayWithoutFiles(['id'], ['Children' => ['email']], true);
        static::assertEquals(
            [
                'id' => $insertedRecords[0]['id'],
                'Children' => [
                    ['email' => $insertedRecords[1]['email']],
                    ['email' => $insertedRecords[2]['email']]
                ]
            ],
            $toArrayRelation
        );
    }

    public function testIsValueFromDb() {
        $rec = TestingAdmin::newEmptyRecord();
        $rec->setValue('parent_id', 1, false);
        /** @var DbRecordValue $val */
        $val = $this->callObjectMethod($rec, 'getValueObject', 'parent_id');
        static::assertFalse($val->isItFromDb());
        static::assertFalse($rec->isValueFromDb('parent_id'));
        $rec
            ->setValue('id', 1, true)
            ->setValue('parent_id', 2, true);
        static::assertTrue($val->isItFromDb());
        static::assertTrue($rec->isValueFromDb('parent_id'));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $data argument contains unknown column name or relation name: '0'
     */
    public function testInvalidFromData1() {
        TestingAdmin::fromArray(['unknown_col']);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage $data argument contains unknown column name or relation name: 'unknown_col'
     */
    public function testInvalidFromData2() {
        TestingAdmin::fromArray(['unknown_col' => 1]);
    }

    /**
     * @expectedException PeskyORM\ORM\Exception\InvalidDataException
     * @expectedExceptionMessage Validation errors: [id] Value must be of an integer data type
     */
    public function testInvalidFromData3() {
        TestingAdmin::fromArray(['id' => 'qqqq'], true);
    }

    public function testFromData() {
        $adminWithId = $this->getDataForSingleAdmin(true);
        $normalizedAdminWithId = $this->normalizeAdmin($adminWithId, null);
        $adminWithoutId = $this->getDataForSingleAdmin(false);
        $normalizedAdminWithoutId = $this->normalizeAdmin($adminWithoutId, null);
        $columns = array_merge(array_keys($adminWithId), ['password', 'not_existing_column', 'not_changeable_column']);

        $rec = TestingAdmin::fromArray([]);
        static::assertEquals($rec->getDefaults($columns, false), $rec->toArrayWithoutFiles());

        $rec = TestingAdmin::fromArray($adminWithoutId, false);
        static::assertEquals($normalizedAdminWithoutId, $rec->toArrayWithoutFiles());
        static::assertFalse($rec->isValueFromDb('parent_id'));

        $rec = TestingAdmin::_()->fromData($adminWithoutId, false);
        static::assertEquals($normalizedAdminWithoutId, $rec->toArrayWithoutFiles());
        static::assertFalse($rec->isValueFromDb('parent_id'));

        $rec = TestingAdmin::fromArray($adminWithId, true);
        static::assertEquals($normalizedAdminWithId, $rec->toArrayWithoutFiles());
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));

        $rec = TestingAdmin::_()->fromDbData($adminWithId);
        static::assertEquals($normalizedAdminWithId, $rec->toArrayWithoutFiles());
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));

        $withUnknownColumn = array_merge($adminWithId, ['unknown_col' => 1]);
        TestingAdmin::_()->fromData($withUnknownColumn, true, false);
        static::assertEquals($normalizedAdminWithId, $rec->toArrayWithoutFiles());
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
    }

    public function testFromPrimaryKey() {
        $recordsAdded = TestingApp::fillAdminsTable(10);
        $recordsAdded[0]['not_existing_column'] = null;
        $example = $recordsAdded[1];
        unset($example['password'], $example['created_at'], $example['updated_at']);
        $normalColumns = array_diff(array_keys(TestingAdmin::getColumnsThatExistInDb()), ['password', 'created_at', 'updated_at']);
        $shortSetOfColumns = ['id', 'parent_id', 'login'];

        $rec = TestingAdmin::newEmptyRecord()->fromPrimaryKey($example['id']);
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals($example, $rec->toArray($normalColumns));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));

        $rec = TestingAdmin::newEmptyRecord()->fromPrimaryKey($example['id'], $shortSetOfColumns);
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals(array_intersect_key($example, array_flip($shortSetOfColumns)), $rec->toArray($shortSetOfColumns));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertTrue($rec->isValueFromDb('login'));
        static::assertFalse($rec->isValueFromDb('email'));

        $rec = TestingAdmin::read($example['id']);
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals($example, $rec->toArray($normalColumns));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));

        $rec = TestingAdmin::read($example['id'], $shortSetOfColumns);
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals(array_intersect_key($example, array_flip($shortSetOfColumns)), $rec->toArray($shortSetOfColumns));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertTrue($rec->isValueFromDb('login'));
        static::assertFalse($rec->isValueFromDb('email'));

        $rec = TestingAdmin::read(99999);
        static::assertFalse($rec->existsInDb());
        static::assertFalse($rec->existsInDb(true));
        static::assertFalse($rec->hasValue('id'));

        // get related records
        $rec = TestingAdmin::read($example['id'], [], ['Parent', 'Children']);
        static::assertTrue($rec->existsInDb());
        $relatedRecords = static::getObjectPropertyValue($rec, 'relatedRecords');
        static::assertCount(2, $relatedRecords);
        static::assertArrayHasKey('Parent', $relatedRecords);
        static::assertArrayHasKey('Children', $relatedRecords);
        static::assertInstanceOf(TestingAdmin::class, $relatedRecords['Parent']);
        static::assertInstanceOf(TestingAdmin::class, $rec->getRelatedRecord('Parent', false));
        static::assertInstanceOf(\PeskyORM\ORM\DbRecordsArray::class, $relatedRecords['Children']);
        static::assertInstanceOf(\PeskyORM\ORM\DbRecordsArray::class, $rec->getRelatedRecord('Children', false));
        static::assertEquals($recordsAdded[0], $rec->getRelatedRecord('Parent', false)->toArrayWithoutFiles());
        static::assertCount(2, $relatedRecords['Children']);
        static::assertCount(2, $rec->getRelatedRecord('Children', false));
        $expected = [$recordsAdded[3], $recordsAdded[7]];
        $expected[0]['created_at'] .= '+00';
        $expected[0]['updated_at'] .= '+00';
        $expected[1]['created_at'] .= '+00';
        $expected[1]['updated_at'] .= '+00';
        static::assertEquals($expected, $rec->getRelatedRecord('Children', false)->toArrays());
    }

    // todo: test exceptions genereated by TestingAdmin::newEmptyRecord()->fromDb()

    public function testFromDb() {
        TestingApp::clearTables();
        $recordsAdded = TestingApp::fillAdminsTable(10);
        $example = $recordsAdded[0];
        $exampleWithParent = $recordsAdded[1];
        unset(
            $example['password'], $example['created_at'], $example['updated_at'],
            $exampleWithParent['password'], $exampleWithParent['created_at'], $exampleWithParent['updated_at']
        );
        $normalColumns = array_diff(array_keys(TestingAdmin::getColumnsThatExistInDb()), ['password', 'created_at', 'updated_at']);
        $shortSetOfColumns = ['id', 'parent_id', 'email'];

        $rec = TestingAdmin::newEmptyRecord()->fromDb(['email' => $example['email']]);
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertEquals($example['email'], $rec->getValue('email'));
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals($example, $rec->toArray($normalColumns));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));

        $rec = TestingAdmin::newEmptyRecord()->fromDb(['email' => $example['email']], $shortSetOfColumns);
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertEquals($example['email'], $rec->getValue('email'));
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals(array_intersect_key($example, array_flip($shortSetOfColumns)), $rec->toArray($shortSetOfColumns));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));

        $rec = TestingAdmin::find(['email' => $example['email']]);
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertEquals($example['email'], $rec->getValue('email'));
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals($example, $rec->toArray($normalColumns));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));

        $rec = TestingAdmin::find(['email' => $example['email']], $shortSetOfColumns);
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertEquals($example['email'], $rec->getValue('email'));
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals(array_intersect_key($example, array_flip($shortSetOfColumns)), $rec->toArray($shortSetOfColumns));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));

        $rec = TestingAdmin::find(['email' => 'abrakadabra@abrakada.bra']);
        static::assertFalse($rec->existsInDb());
        static::assertFalse($rec->existsInDb(true));
        static::assertFalse($rec->hasValue('id'));
        static::assertFalse($rec->hasValue('email'));

        // relations
        $rec = TestingAdmin::find(['id' => $exampleWithParent['id']], $shortSetOfColumns, ['Parent', 'Children']);
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertNotNull($rec->getValue('parent_id'));
        static::assertEquals(array_intersect_key($exampleWithParent, array_flip($shortSetOfColumns)), $rec->toArray($shortSetOfColumns));
        static::assertEquals($example, $rec->getRelatedRecord('Parent', false)->toArray($normalColumns));
        $children = $rec->getRelatedRecord('Children', false);
        static::assertCount(2, $children->toArrays());
        static::assertEquals([$recordsAdded[3]['id'], $recordsAdded[7]['id']], \Swayok\Utils\Set::extract('/id', $children->toArrays()));

        $rec = TestingAdmin::find(['id' => $recordsAdded[0]['id']], $shortSetOfColumns, ['Parent']);
        static::assertTrue($rec->existsInDb());
        static::assertEquals(array_intersect_key($example, array_flip($shortSetOfColumns)), $rec->toArray($shortSetOfColumns));
        static::assertFalse($rec->getRelatedRecord('Parent', false)->existsInDb());
    }

    // todo: test exceptions thrown by reload()

    public function testReload() {
        TestingApp::clearTables();
        $recordsAdded = TestingApp::fillAdminsTable(10);
        $example = $recordsAdded[0];
        $normalColumns = array_diff(array_keys(TestingAdmin::getColumnsThatExistInDb()), ['password', 'created_at', 'updated_at']);
        unset($example['password'], $example['created_at'], $example['updated_at']);

        $rec = TestingAdmin::newEmptyRecord()->setValue('id', $example['id'], true);
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertFalse($rec->hasValue('email'));

        $rec->reload(['email']);
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertTrue($rec->hasValue('email'));
        static::assertEquals($example['email'], $rec->getValue('email'));
        static::assertFalse($rec->hasValue('parent_id'));

        $rec->reload();
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertTrue($rec->hasValue('email'));
        static::assertEquals($example, $rec->toArray($normalColumns));

        // test relations
        $rec->reload([], ['Parent', 'Children']);
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertTrue($rec->isRelatedRecordAttached('Children'));
        static::assertFalse($rec->getRelatedRecord('Parent', false)->existsInDb());
        static::assertCount(2, $rec->getRelatedRecord('Children', false)->toArrays());
        static::assertEquals(
            [$recordsAdded[1]['id'], $recordsAdded[2]['id']],
            \Swayok\Utils\Set::extract('/id', $rec->getRelatedRecord('Children', false)->toArrays())
        );
    }

    // todo: test exceptions thrown by readColumns()

    public function testReadColumns() {
        TestingApp::clearTables();
        $recordsAdded = TestingApp::fillAdminsTable(1);
        $example = $recordsAdded[0];

        $rec = TestingAdmin::read($example['id'], ['id']);
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertFalse($rec->hasValue('email'));
        static::assertFalse($rec->hasValue('login'));

        $rec->readColumns(['email', 'login']);
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertTrue($rec->hasValue('email'));
        static::assertEquals($example['email'], $rec->getValue('email'));
        static::assertTrue($rec->hasValue('login'));
        static::assertEquals($example['login'], $rec->getValue('login'));
        static::assertFalse($rec->hasValue('parent_id'));
        static::assertFalse($rec->hasValue('language'));
    }

    // todo: test exceptions thrown by setRelatedRecord();
    // todo: test exceptions thrown by getRelatedRecord();
    // todo: test exceptions thrown by hasRelatedRecord();

    public function testSetGetAndHasRelatedRecord() {
        TestingApp::clearTables();
        $records = TestingApp::getRecordsForDb('admins', 2);
        $parentData = $records[0];
        $recordData = $records[1];
        $normalColumns = array_diff(array_keys(TestingAdmin::getColumnsThatExistInDb()), ['password', 'created_at', 'updated_at']);
        unset(
            $parentData['password'], $parentData['created_at'], $parentData['updated_at'],
            $recordData['password'], $recordData['created_at'], $recordData['updated_at']
        );
        $normalizedParentData = $this->normalizeAdmin($parentData, true, false);
        $normalizedRecordData = $this->normalizeAdmin($recordData, true, false);
        unset($normalizedParentData['password'], $normalizedRecordData['password']);

        $rec = TestingAdmin::fromArray($recordData, true);
        $this->callObjectMethod($rec, 'setRelatedRecord', 'Parent', $parentData, true);
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertEquals($normalizedParentData, $rec->getRelatedRecord('Parent', false)->toArrayWithoutFiles($normalColumns));
        static::assertTrue($rec->getRelatedRecord('Parent', false)->existsInDb());

        $rec = TestingAdmin::fromArray(array_merge($recordData, ['Parent' => $parentData]), true);
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertEquals($normalizedParentData, $rec->getRelatedRecord('Parent', false)->toArrayWithoutFiles($normalColumns));
        static::assertTrue($rec->getRelatedRecord('Parent', false)->existsInDb());
        static::assertEquals(
            array_merge($normalizedRecordData, ['Parent' => $normalizedParentData]),
            $rec->toArrayWithoutFiles($normalColumns, ['Parent' => $normalColumns], false)
        );
    }

    // todo: test exceptions thrown by readRelatedRecord();

    public function testReadRelatedRecord() {
        $recordsAdded = TestingApp::fillAdminsTable(10);
        $parentData = $recordsAdded[0];
        $normalColumns = array_diff(array_keys(TestingAdmin::getColumnsThatExistInDb()), ['created_at', 'updated_at']);
        unset($parentData['created_at'], $parentData['updated_at']);

        $rec = TestingAdmin::fromArray($recordsAdded[1], true);
        static::assertFalse($rec->isRelatedRecordAttached('Parent'));
        static::assertFalse($rec->isRelatedRecordAttached('Children'));
        static::assertEquals($parentData['id'], $rec->getValue('parent_id'));
        $prevSqlQuery = TestingAdminsTable::getLastQuery();
        static::assertTrue($rec->getRelatedRecord('Parent', true)->existsInDb());
        static::assertNotEquals($prevSqlQuery, TestingAdminsTable::getLastQuery());
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertEquals($parentData, $rec->getRelatedRecord('Parent', false)->toArray($normalColumns));
        $prevSqlQuery = TestingAdminsTable::getLastQuery();
        static::assertInstanceOf(\PeskyORM\ORM\DbRecordsSet::class, $rec->getRelatedRecord('Children', true));
        static::assertEquals($prevSqlQuery, TestingAdminsTable::getLastQuery()); //< DbRecordsSet is lazy - query is still the same
        static::assertCount(2, $rec->getRelatedRecord('Children', true));
        static::assertNotEquals($prevSqlQuery, TestingAdminsTable::getLastQuery()); //< count mades a query
        $prevSqlQuery = TestingAdminsTable::getLastQuery();
        static::assertEquals(
            [$recordsAdded[3]['id'], $recordsAdded[7]['id']],
            \Swayok\Utils\Set::extract('/id', $rec->getRelatedRecord('Children', false)->toArrays())
        );
        static::assertNotEquals($prevSqlQuery, TestingAdminsTable::getLastQuery()); //< and now it was a query to get records data

        // change id and test if relations were erased
        $rec->setValue('id', $parentData['id'], true);
        static::assertFalse($rec->isRelatedRecordAttached('Parent'));
        static::assertFalse($rec->isRelatedRecordAttached('Children'));
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $data argument contains unknown column name or relation name: 'invalid_col'
     */
    public function testInvalidUpdateValuesData1() {
        TestingAdmin::newEmptyRecord()->updateValues(['id' => 1, 'invalid_col' => 2], true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $relatedRecord argument must be an array or instance of DbRecord class for the 'admins' DB table
     */
    public function testInvalidUpdateValuesData2() {
        TestingAdmin::newEmptyRecord()->updateValues(['id' => 1, 'Parent' => null, 'Parent2' => null], true);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $data argument contains unknown column name or relation name: 'Parent2'
     */
    public function testInvalidUpdateValuesData3() {
        TestingAdmin::newEmptyRecord()->updateValues(['id' => 1, 'Parent' => [], 'Parent2' => null], true);
    }

    /**
     * @expectedException \PeskyORM\ORM\Exception\InvalidDataException
     * @expectedExceptionMessage Validation errors: [email] Value must be an email
     */
    public function testInvalidUpdateValuesData4() {
        TestingAdmin::newEmptyRecord()->updateValues(['email' => 'not email']);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Values update failed: record does not exist in DB while $isFromDb argument is 'true'. Possibly you've missed a primary key value in $data argument.
     */
    public function testInvalidUpdateValuesData5() {
        TestingAdmin::newEmptyRecord()->updateValues(['email' => 'test@email.cc'], true);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage It is forbidden to set primary key value when $isFromDb === false
     */
    public function testInvalidUpdateValuesData6() {
        TestingAdmin::newEmptyRecord()->updateValues(['id' => 1], false);
    }

    public function testUpdateValues() {
        $records = TestingApp::getRecordsForDb('admins', 10);
        $rec = TestingAdmin::fromArray($records[1], true);
        static::assertFalse($rec->isRelatedRecordAttached('Parent'));
        static::assertEquals($records[1]['email'], $rec->getValue('email'));

        $rec->updateValues(['email' => 'changed' . $records[1]['email']]);
        static::assertEquals('changed' . $records[1]['email'], $rec->getValue('email'));
        static::assertFalse($rec->isValueFromDb('email'));

        $rec->updateValues(['email' => 'changed2' . $records[1]['email']], true);
        static::assertEquals('changed2' . $records[1]['email'], $rec->getValue('email'));
        static::assertTrue($rec->isValueFromDb('email'));

        $rec->updateValues(['Parent' => array_diff_key($records[0], ['id' => '', 'not_changeable_column' => ''])]);
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertFalse($rec->getRelatedRecord('Parent', false)->existsInDb(false));

        $rec->updateValues(['Parent' => $records[0]], true);
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertTrue($rec->getRelatedRecord('Parent', false)->existsInDb(false));
        static::assertEquals(
            array_merge($records[0], ['not_existing_column' => null]),
            $rec->getRelatedRecord('Parent', false)->toArrayWithoutFiles()
        );

        $rec->merge(['Parent' => [], 'email' => null], false);
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertFalse($rec->getRelatedRecord('Parent', false)->existsInDb(false));
        static::assertNull($rec->getValue('email'));
        static::assertFalse($rec->isValueFromDb('email'));

        $rec->merge(['invalid_column' => 1, 'email' => 'qqqq@qqqq.qq'], false, false);
    }

    public function testGetAllColumnsWithUpdatableValues() {
        $rec = TestingAdmin::newEmptyRecord();
        $updateable = $this->callObjectMethod($rec, 'getAllColumnsWithUpdatableValues');
        static::assertContains('id', $updateable);
        static::assertContains('updated_at', $updateable);
        static::assertContains('created_at', $updateable);
        static::assertContains('avatar', $updateable);
        static::assertContains('some_file', $updateable);
        static::assertNotContains('not_changeable_column', $updateable);
        static::assertNotContains('not_existing_column', $updateable);
    }

    public function testGetAllColumnsWithAutoUpdatingValues() {
        $rec = TestingAdmin::newEmptyRecord();
        $updateable = $this->callObjectMethod($rec, 'getAllColumnsWithAutoUpdatingValues');
        static::assertEquals(['updated_at'], $updateable);
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Attempt to save data after begin(). You must call commit() or rollback()
     */
    public function testInvalidSave() {
        TestingAdmin::fromArray(['id' => 1], true)->begin()->save();
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnsToSave argument contains unknown columns: qwejklqe, asdqwe, Array
     */
    public function testInvalidSaveToDb1() {
        $this->callObjectMethod(
            TestingAdmin::fromArray(['id' => 1], true),
            'saveToDb',
            ['id', 'qwejklqe', 'asdqwe', ['qqq']]
        );
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $columnsToSave argument contains columns that cannot be saved to DB: not_changeable_column, not_existing_column
     */
    public function testInvalidSaveToDb2() {
        $this->callObjectMethod(
            TestingAdmin::fromArray(['id' => 1], true),
            'saveToDb',
            ['id', 'some_file', 'not_changeable_column', 'not_existing_column']
        );
    }

    /**
     * @expectedException \PeskyORM\ORM\Exception\InvalidDataException
     * @expectedExceptionMessage Validation errors: [email] Value must be an email
     */
    public function testInvalidDataInCollectValuesForSave() {
        $this->callObjectMethod(
            TestingAdmin::fromArray(['id' => 1, 'parent_id' => null, 'email' => 'invalid', 'login' => 'asd'], true),
            'collectValuesForSave',
            ['parent_id', 'email', 'login'],
            false
        );
    }

    /**
     * @covers DbRecord::collectValuesForSave()
     * @covers DbRecord::validateNewData()
     * @covers DbRecord::validateValue()
     */
    public function testCollectValuesForSaveAndValidateNewData() {
        static::assertEquals(['Value must be an email'], TestingAdmin::validateValue('email', 'invalid'));
        static::assertEquals([], TestingAdmin::validateValue('email', 'valid@email.cc'));

        $originalData = TestingApp::getRecordsForDb('admins', 1)[0];
        $rec = TestingAdmin::fromArray($originalData, true);
        $columnsToSave = $this->callObjectMethod($rec, 'getAllColumnsWithUpdatableValues');
        static::assertEquals(
            [],
            $this->callObjectMethod($rec, 'collectValuesForSave', $columnsToSave, true)
        );
        $updates = array_diff_key($originalData, array_flip(['id', 'not_changeable_column', 'password']));
        $rec->updateValues($updates, false);
        static::assertEquals(
            [], //< data actually was not modified for any column
            $this->callObjectMethod($rec, 'collectValuesForSave', $columnsToSave, true)
        );
        $rec->reset()->setValue('id', $originalData['id'], true);
        $rec->updateValues($updates);
        $expectedData = array_merge($updates, ['id' => $originalData['id'], 'updated_at' => DbExpr::create('NOW()')]);
        static::assertEquals(
            $expectedData,
            $this->callObjectMethod($rec, 'collectValuesForSave', $columnsToSave, true)
        );
        static::assertEquals(
            [],
            $this->callObjectMethod($rec, 'validateNewData', $expectedData, $columnsToSave, true)
        );

        $rec->unsetPrimaryKeyValue();
        $expectedData = array_merge(
            $originalData,
            $expectedData,
            ['id' => TestingAdminsTable::getExpressionToSetDefaultValueForAColumn()]
        );
        unset($expectedData['not_changeable_column'], $expectedData['password']);
        static::assertEquals(
            $expectedData,
            $this->callObjectMethod($rec, 'collectValuesForSave', $columnsToSave, false)
        );
        static::assertEquals(
            ['password' => ['Null value is not allowed']],
            $this->callObjectMethod($rec, 'validateNewData', $expectedData, $columnsToSave, false)
        );

        $rec->reset();
        $defaults = array_filter($rec->getDefaults($columnsToSave, true, false), function ($value) {
            return $value !== null;
        });
        $expectedData = array_merge(
            [
                'id' => TestingAdminsTable::getExpressionToSetDefaultValueForAColumn(),
                'updated_at' => DbExpr::create('NOW()'),
            ],
            $defaults
        );
        static::assertEquals(
            $expectedData,
            $this->callObjectMethod($rec, 'collectValuesForSave', $columnsToSave, false)
        );
        static::assertEquals(
            ['login' => ['Null value is not allowed'], 'password' => ['Null value is not allowed']],
            $this->callObjectMethod($rec, 'validateNewData', $expectedData, $columnsToSave, false)
        );

        $rec->reset();
        $expectedData = ['id' => 1, 'parent_id' => null, 'email' => 'test@test.cc', 'login' => 'test'];
        $rec->fromData($expectedData, true);
        static::assertEquals(
            [],
            $this->callObjectMethod($rec, 'collectValuesForSave', $columnsToSave, true)
        );

        static::assertEquals(
            ['email' => ['Value must be an email']],
            $this->callObjectMethod($rec, 'validateNewData', ['email' => 'invalid'], ['email'], true)
        );
        static::assertEquals(
            ['email' => ['Value must be an email']],
            $this->callObjectMethod($rec, 'validateNewData', ['email' => 'invalid'], ['email'], false)
        );
    }

    /**
     * @covers DbRecord::beforeSave()
     * @expectedException \PeskyORM\ORM\Exception\InvalidDataException
     * @expectedExceptionMessage Validation errors: [login] error
     */
    public function testBeforeSave() {
        TestingApp::clearTables();
        $rec = \PeskyORMTest\TestingAdmins\TestingAdmin2::newEmptyRecord();
        $rec
            ->fromData(['id' => 999, 'login' => 'qqq'], true)
            ->setValue('password', 'test', false)
            ->save();
        TestingApp::clearTables();
    }

    /**
     * @covers DbRecord::afterSave()
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage after: no-no-no!
     */
    public function testAfterSave() {
        TestingApp::clearTables();
        $rec = \PeskyORMTest\TestingAdmins\TestingAdmin2::newEmptyRecord();
        $rec
            ->setValue('login', 'test', false)
            ->setValue('password', 'test', false)
            ->save();
        TestingApp::clearTables();
    }

    /**
     * @covers DbRecord::runColumnSavingExtenders()
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage login: update!
     */
    public function testColumnSavingExtenders1() {
        $rec = \PeskyORMTest\TestingAdmins\TestingAdmin3::newEmptyRecord();
        $this->callObjectMethod(
            $rec,
            'runColumnSavingExtenders',
            ['id', 'parent_id', 'login'],
            ['id' => 1, 'parent_id' => null, 'login' => 'test'],
            ['id' => 1, 'parent_id' => null, 'login' => 'test'],
            true
        );
    }

    /**
     * @covers DbRecord::runColumnSavingExtenders()
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage some_file: here
     */
    public function testColumnSavingExtenders2() {
        $rec = \PeskyORMTest\TestingAdmins\TestingAdmin3::newEmptyRecord();
        $this->callObjectMethod(
            $rec,
            'runColumnSavingExtenders',
            ['parent_id', 'login'],
            ['parent_id' => null, 'login' => 'test'],
            ['id' => 1, 'parent_id' => null, 'login' => 'test'],
            false
        );

        $this->callObjectMethod(
            $rec,
            'runColumnSavingExtenders',
            ['parent_id', 'some_file'],
            ['parent_id' => null],
            ['id' => 1, 'parent_id' => null],
            false
        );
    }

    /**
     * @covers DbRecord::runColumnSavingExtenders()
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage login: update!
     */
    public function testColumnSavingExtendersUsageInSave1() {
        TestingApp::fillAdminsTable(1);
        $rec = \PeskyORMTest\TestingAdmins\TestingAdmin3::newEmptyRecord()
            ->fromData(['id' => 1], true)
            ->updateValues(['parent_id' => null, 'login' => 'test']);
        $rec->save();
        TestingApp::clearTables();
    }

    /**
     * @covers DbRecord::runColumnSavingExtenders()
     * @expectedException UnexpectedValueException
     * @expectedExceptionMessage some_file: here
     */
    public function testColumnSavingExtendersUsageInSave2() {
        TestingApp::fillAdminsTable(1);
        $rec = \PeskyORMTest\TestingAdmins\TestingAdmin3::newEmptyRecord()
            ->fromData(['id' => 1], true)
            ->updateValues([
                'parent_id' => null,
                'some_file' => [
                    'tmp_name' => __DIR__ . '/files/test_file.jpg',
                    'name' => 'image.jpg',
                    'type' => 'image/jpeg',
                    'size' => filesize(__DIR__ . '/files/test_file.jpg'),
                    'error' => 0,
                ]
            ]);
        $rec->save();
        TestingApp::clearTables();
    }

    /**
     * @covers DbRecord::save()
     * @covers DbRecord::saveToDb()
     */
    public function testSaveAndSaveToDbAndBeforeAfterSave() {
        $recordsAdded = TestingApp::fillAdminsTable(10);
        static::assertEquals(10, TestingAdminsTable::count([]));
        $rec = TestingAdmin::newEmptyRecord();
        // insert
        $newRec = array_diff_key($recordsAdded[0], array_flip(['id', 'not_changeable_column', 'password']));
        $rec->fromData($newRec)->setValue('password', 'test', false)->save();
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertNotEquals($newRec['updated_at'], $rec->getValue('updated_at'));
        static::assertTrue(password_verify('test', $rec->getValue('password')));
        unset($newRec['updated_at']);
        static::assertEquals($newRec, $rec->toArrayWithoutFiles(array_keys($newRec)));
        static::assertEquals(11, TestingAdminsTable::count([]));
        // update
        $rec->fromData($recordsAdded[1], true)->updateValues($newRec)->save();
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals($recordsAdded[1]['password'], $rec->getValue('password'));
        static::assertEquals($newRec, $rec->toArrayWithoutFiles(array_keys($newRec)));
        static::assertEquals(11, TestingAdminsTable::count([]));
        // update not exising id
        $rec->setValue('id', 0, true)->save();
        static::assertFalse($rec->existsInDb());
        static::assertFalse($rec->existsInDb(true));
        static::assertEquals(11, TestingAdminsTable::count([]));
        // relations saving
        $rec = TestingAdmin::fromArray($newRec, false)->setValue('password', 'test1', false);
        $child1 = array_merge($recordsAdded[1], ['parent_id' => null, 'id' => null, 'password' => 'test']);
        $child2 = array_merge($recordsAdded[2], ['parent_id' => null, 'id' => null, 'password' => 'test2']);
        unset($child1['not_changeable_column'], $child2['not_changeable_column']);
        $rec->updateValues(['Children' => [$child1, $child2]], false);
        static::assertEquals(11, TestingAdminsTable::count());
        $rec->save(['Children']);
        static::assertEquals(14, TestingAdminsTable::count());
        static::assertNull($rec->getValue('parent_id')); //< should not be changed
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertCount(2, $rec->getRelatedRecord('Children'));
        $rec->reload([], ['Children']);
        static::assertNull($rec->getValue('parent_id'));
        $expected1 = array_diff_key($child1, array_flip(['id', 'updated_at', 'password']));
        $expected2 = array_diff_key($child2, array_flip(['id', 'updated_at', 'password']));
        $expected1['parent_id'] = $expected2['parent_id'] = $rec->getPrimaryKeyValue();
        static::assertEquals(
            $expected1,
            $rec->getRelatedRecord('Children')[0]->toArrayWithoutFiles(array_keys($expected1))
        );
        static::assertEquals(
            $expected2,
            $rec->getRelatedRecord('Children')[1]->toArrayWithoutFiles(array_keys($expected1))
        );
        static::assertEquals($rec->getPrimaryKeyValue(), $rec->getRelatedRecord('Children')[0]->getValue('parent_id'));
        static::assertEquals($rec->getPrimaryKeyValue(), $rec->getRelatedRecord('Children')[1]->getValue('parent_id'));
        static::assertTrue(password_verify('test', $rec->getRelatedRecord('Children')[0]->getValue('password')));
        static::assertTrue(password_verify('test2', $rec->getRelatedRecord('Children')[1]->getValue('password')));
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Trying to begin collecting changes on not existing record
     */
    public function testInvalidBegin1() {
        TestingAdmin::newEmptyRecord()->begin();
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Trying to begin collecting changes on not existing record
     */
    public function testInvalidBegin2() {
        TestingAdmin::fromArray(['parent_id' => 1], false)->begin();
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Attempt to begin collecting changes when already collecting changes
     */
    public function testInvalidBegin3() {
        TestingAdmin::fromArray(['id' => 1], true)->begin()->begin();
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Attempt to reset record while changes collecting was not finished. You need to use commit() or rollback() first
     */
    public function testInvalidBegin4() {
        TestingAdmin::fromArray(['id' => 1], true)->begin()->reset();
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage It is impossible to rollback changed values: changes collecting was not started
     */
    public function testInvalidRollback1() {
        TestingAdmin::newEmptyRecord()->rollback();
    }

    public function testBeginAndRollback() {
        $rec = TestingAdmin::fromArray(['id' => 1], true);
        $rec->begin();
        static::assertTrue($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertEquals([], $this->getObjectPropertyValue($rec, 'valuesBackup'));
        $rec->rollback();
        static::assertFalse($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertEquals([], $this->getObjectPropertyValue($rec, 'valuesBackup'));
        $rec->begin();
        $rec->setValue('email', 'email.was@changed.hehe', false);
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('email', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertInstanceOf(DbRecordValue::class, $this->getObjectPropertyValue($rec, 'valuesBackup')['email']);
        static::assertFalse($this->getObjectPropertyValue($rec, 'valuesBackup')['email']->hasValue());
        static::assertEquals('email.was@changed.hehe', $rec->getValue('email'));
        static::assertFalse($this->callObjectMethod($rec, 'getValueObject', 'email')->hasOldValue());
        $rec->rollback();
        static::assertFalse($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertFalse($rec->hasValue('email'));
        static::assertFalse($this->callObjectMethod($rec, 'getValueObject', 'email')->hasOldValue());

        $rec->begin();
        $rec->setValue('email', 'email.was@changed.hehe', false);
        $rec->setValue('login', 'email.was@changed.hehe', false);
        static::assertCount(2, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('email', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('login', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertInstanceOf(DbRecordValue::class, $this->getObjectPropertyValue($rec, 'valuesBackup')['email']);
        static::assertInstanceOf(DbRecordValue::class, $this->getObjectPropertyValue($rec, 'valuesBackup')['login']);
        static::assertFalse($this->getObjectPropertyValue($rec, 'valuesBackup')['email']->hasValue());
        static::assertFalse($this->getObjectPropertyValue($rec, 'valuesBackup')['login']->hasValue());
        static::assertEquals('email.was@changed.hehe', $rec->getValue('email'));
        static::assertEquals('email.was@changed.hehe', $rec->getValue('login'));
        static::assertFalse($this->callObjectMethod($rec, 'getValueObject', 'email')->hasOldValue());
        static::assertFalse($this->callObjectMethod($rec, 'getValueObject', 'login')->hasOldValue());
        $rec->rollback();
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertFalse($rec->hasValue('email'));
        static::assertFalse($rec->hasValue('login'));
        static::assertFalse($this->callObjectMethod($rec, 'getValueObject', 'email')->hasOldValue());
        static::assertFalse($this->callObjectMethod($rec, 'getValueObject', 'login')->hasOldValue());

        $data = TestingApp::getRecordsForDb('admins', 1)[0];
        $data = array_merge($data, ['not_existing_column' => null]);
        $rec->fromData($data, true); //< it should not fail even if there was no commit() or rollback() after previous begin()
        static::assertEquals($data, $rec->toArrayWithoutFiles());
        $rec->begin()->rollback()->begin();
        $rec->setValue('email', 'email.was@changed.hehe', false);
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('email', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertEquals($data['email'], $this->getObjectPropertyValue($rec, 'valuesBackup')['email']->getValue());
        static::assertEquals('email.was@changed.hehe', $rec->getValue('email'));
        static::assertEquals($data['email'], $this->callObjectMethod($rec, 'getValueObject', 'email')->getOldValue());
        $rec->rollback();
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertTrue($rec->hasValue('email'));
        static::assertEquals($data['email'], $rec->getValue('email'));
        static::assertFalse($this->callObjectMethod($rec, 'getValueObject', 'email')->hasOldValue());
        static::assertEquals($data, $rec->toArrayWithoutFiles());

        $rec->begin();
        static::assertEquals($data, $rec->toArrayWithoutFiles());
        $rec->setValue('email', 'email.was@changed.hehe', false);
        $rec->setValue('login', 'email.was@changed.hehe', false);
        static::assertCount(2, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('email', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('login', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertEquals($data['email'], $this->getObjectPropertyValue($rec, 'valuesBackup')['email']->getValue());
        static::assertEquals($data['login'], $this->getObjectPropertyValue($rec, 'valuesBackup')['login']->getValue());
        static::assertEquals('email.was@changed.hehe', $rec->getValue('email'));
        static::assertEquals('email.was@changed.hehe', $rec->getValue('login'));
        static::assertEquals($data['email'], $this->callObjectMethod($rec, 'getValueObject', 'email')->getOldValue());
        static::assertEquals($data['login'], $this->callObjectMethod($rec, 'getValueObject', 'login')->getOldValue());
        $rec->rollback();
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertTrue($rec->hasValue('email'));
        static::assertTrue($rec->hasValue('login'));
        static::assertEquals($data, $rec->toArrayWithoutFiles());
        static::assertFalse($this->callObjectMethod($rec, 'getValueObject', 'email')->hasOldValue());
        static::assertFalse($this->callObjectMethod($rec, 'getValueObject', 'login')->hasOldValue());
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage It is impossible to commit changed values: changes collecting was not started
     */
    public function testInvalidCommit1() {
        TestingAdmin::newEmptyRecord()->commit();
    }

    /**
     * @covers DbRecord::commit()
     */
    public function testCommit() {
        $recordsAdded = TestingApp::fillAdminsTable(10);
        static::assertEquals(10, TestingAdminsTable::count([]));
        $rec = TestingAdmin::fromArray($recordsAdded[2], true);
        $expected = array_diff_key($recordsAdded[2], array_flip(['updated_at', 'password']));
        static::assertEquals($expected, $rec->toArrayWithoutFiles(array_keys($expected)));
        $update = array_diff_key($recordsAdded[0], array_flip(['id', 'password', 'not_changeable_column']));
        $rec
            ->begin()
            ->updateValues($update, false)
            ->commit()
            ->reload();
        unset($update['updated_at']);
        $expected = array_merge($expected, $update);
        static::assertEquals($expected, $rec->toArrayWithoutFiles(array_keys($expected)));
        static::assertNotEquals($recordsAdded[2]['updated_at'], $rec->getValue('updated_at'));
        static::assertNotEquals($recordsAdded[0]['updated_at'], $rec->getValue('updated_at'));
        static::assertEquals($recordsAdded[2]['password'], $rec->getValue('password'));
        static::assertEquals($recordsAdded[2]['not_changeable_column'], $rec->getValue('not_changeable_column'));
        // test password change
        $rec
            ->begin()
            ->setValue('password', 'test1111', false)
            ->commit();
        static::assertEquals(array_merge($expected, $update), $rec->toArrayWithoutFiles(array_keys($expected)));
        static::assertNotEquals($recordsAdded[0]['password'], $rec->getValue('password'));
        static::assertNotEquals($recordsAdded[2]['password'], $rec->getValue('password'));
        static::assertTrue(password_verify('test1111', $rec->getValue('password')));
        static::assertTrue(password_verify('test1111', $rec->reload()->getValue('password')));
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage It is impossible to save related objects of a record that does not exist in DB
     */
    public function testInvalidSaveRelations1() {
        TestingAdmin::newEmptyRecord()->saveRelations(['Parent']);
    }

    /**
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage $relationsToSave argument contains unknown relations: NotRelation, Array
     */
    public function testInvalidSaveRelations2() {
        TestingAdmin::newEmptyRecord()->setValue('id', 1, true)->saveRelations(['NotRelation', ['asd']]);
    }

    public function testSaveRelations() {
        $recordsAdded = TestingApp::fillAdminsTable(10);
        $parent = array_merge($recordsAdded[2], ['parent_id' => null, 'id' => null, 'password' => 'test']);
        unset($parent['not_changeable_column']);
        // belongs to while record exists
        $rec = TestingAdmin::fromArray($recordsAdded[0], true);
        $rec->updateValues(['Parent' => $parent], false);
        static::assertEquals(10, TestingAdminsTable::count());
        $rec->saveRelations(['Parent']);
        static::assertEquals(11, TestingAdminsTable::count());
        static::assertNotNull($rec->getValue('parent_id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertTrue($rec->getRelatedRecord('Parent')->existsInDb(true));
        $rec->reload([], ['Parent']);
        static::assertNotNull($rec->getValue('parent_id'));
        $expected = array_diff_key($parent, array_flip(['id', 'updated_at', 'password']));
        static::assertEquals(
            $expected,
            $rec->getRelatedRecord('Parent')->toArrayWithoutFiles(array_keys($expected))
        );
        static::assertEquals($rec->getValue('parent_id'), $rec->getRelatedRecord('Parent')->getPrimaryKeyValue());
        static::assertTrue(password_verify('test', $rec->getRelatedRecord('Parent')->getValue('password')));

        // belongs to while record not exists - it is forbidden but maybe later
        /*$rec->unsetPrimaryKeyValue();
        $rec->updateValues(['Parent' => $parent], false);
        $rec->saveRelations(['Parent']);
        static::assertEquals(12, TestingAdminsTable::count());
        static::assertNotNull($rec->getValue('parent_id'));
        static::assertFalse($rec->isValueFromDb('parent_id'));*/

        // has one
        TestingApp::fillAdminsTable(1);
        $rec = TestingAdmin::fromArray($recordsAdded[0], true);
        $rec->updateValues(['HasOne' => $parent], false);
        static::assertEquals(1, TestingAdminsTable::count());
        $rec->saveRelations(['HasOne']);
        static::assertEquals(2, TestingAdminsTable::count());
        static::assertNull($rec->getValue('parent_id')); //< should not be changed
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertTrue($rec->getRelatedRecord('HasOne')->existsInDb(true));
        $rec->reload([], ['HasOne']);
        static::assertNull($rec->getValue('parent_id'));
        $expected = array_diff_key($parent, array_flip(['id', 'updated_at', 'password']));
        $expected['parent_id'] = $rec->getPrimaryKeyValue();
        static::assertEquals(
            $expected,
            $rec->getRelatedRecord('HasOne')->toArrayWithoutFiles(array_keys($expected))
        );
        static::assertEquals($rec->getPrimaryKeyValue(), $rec->getRelatedRecord('HasOne')->getValue('parent_id'));
        static::assertTrue(password_verify('test', $rec->getRelatedRecord('HasOne')->getValue('password')));

        // has many
        TestingApp::fillAdminsTable(1);
        $rec = TestingAdmin::fromArray($recordsAdded[0], true);
        $child1 = array_merge($recordsAdded[1], ['parent_id' => null, 'id' => null, 'password' => 'test2']);
        unset($child1['not_changeable_column']);
        $rec->updateValues(['Children' => [$child1, $parent]], false);
        static::assertEquals(1, TestingAdminsTable::count());
        $rec->saveRelations(['Children']);
        static::assertEquals(3, TestingAdminsTable::count());
        static::assertNull($rec->getValue('parent_id')); //< should not be changed
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertCount(2, $rec->getRelatedRecord('Children'));
        $rec->reload([], ['Children']);
        static::assertNull($rec->getValue('parent_id'));
        $expected1 = array_diff_key($child1, array_flip(['id', 'updated_at', 'password']));
        $expected2 = array_diff_key($parent, array_flip(['id', 'updated_at', 'password']));
        $expected1['parent_id'] = $expected2['parent_id'] = $rec->getPrimaryKeyValue();
        static::assertEquals(
            $expected1,
            $rec->getRelatedRecord('Children')[0]->toArrayWithoutFiles(array_keys($expected1))
        );
        static::assertEquals(
            $expected2,
            $rec->getRelatedRecord('Children')[1]->toArrayWithoutFiles(array_keys($expected1))
        );
        static::assertEquals($rec->getPrimaryKeyValue(), $rec->getRelatedRecord('Children')[0]->getValue('parent_id'));
        static::assertEquals($rec->getPrimaryKeyValue(), $rec->getRelatedRecord('Children')[1]->getValue('parent_id'));
        static::assertTrue(password_verify('test2', $rec->getRelatedRecord('Children')[0]->getValue('password')));
        static::assertTrue(password_verify('test', $rec->getRelatedRecord('Children')[1]->getValue('password')));
    }

    /**
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage It is impossible to delete record has no primary key value
     */
    public function testInvalidDelete() {
        TestingAdmin::newEmptyRecord()->delete();
    }

    /**
     * @covers DbRecord::beforeDelete()
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage before delete: no-no-no!
     */
    public function testBeforeDelete() {
        \PeskyORMTest\TestingAdmins\TestingAdmin2::fromArray(['id' => 9999], true)->delete();
    }

    /**
     * @covers DbRecord::afterDelete()
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage after delete: no-no-no!
     */
    public function testAfterDelete() {
        \PeskyORMTest\TestingAdmins\TestingAdmin2::fromArray(['id' => 0], true)->delete();
    }

    /**
     * @covers DbRecord::delete()
     */
    public function testDelete() {
        $addedRecords = TestingApp::fillAdminsTable(10);
        static::assertEquals(10, TestingAdminsTable::count());
        $rec = TestingAdmin::fromArray($addedRecords[1], true);
        $rec->delete();
        static::assertEquals(9, TestingAdminsTable::count());
        static::assertFalse(TestingAdmin::read($addedRecords[1]['id'])->existsInDb());
    }

    /**
     * @covers DbRecord::current()
     * @covers DbRecord::valid()
     * @covers DbRecord::key()
     * @covers DbRecord::next()
     * @covers DbRecord::rewind()
     */
    public function testIterations() {
        /** @var TestingAdmin $rec */
        $rec = TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true);
        $cols = [];
        foreach ($rec as $name => $value) {
            // in iteration it works like iteration over $rec->toArray()
            static::assertEquals($this->callObjectMethod($rec, 'getColumnValueForToArray', $name), $value);
            $cols[] = $name;
        }
        static::assertEquals(array_keys($rec::getColumns()), $cols);
        $count = 0;
        foreach ($rec as $name => $value) {
            // totest rewitnd
            $count++;
        }
        static::assertEquals($count, count($rec::getColumns()));
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Table does not contain column named '0'
     */
    public function testInvalidArrayAccess() {
        /** @var TestingAdmin $rec */
        $rec = TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true);
        $rec[0];
    }

    /**
     * @covers DbRecord::offsetExists()
     * @covers DbRecord::offsetGet()
     * @covers DbRecord::__get()
     * @covers DbRecord::__isset()
     */
    public function testMagicGetterAndArrayAccessGetterAndIsset() {
        /** @var TestingAdmin $rec */
        $rec = TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true);
        foreach ($rec::getColumns() as $name => $config) {
            if (!in_array($name, ['avatar', 'some_file', 'not_existing_column'], true)) {
                static::assertTrue(isset($rec->$name));
                static::assertTrue(isset($rec[$name]));
                static::assertEquals($rec->getValue($name), $rec->$name);
                static::assertEquals($rec->getValue($name), $rec[$name]);
            }
        }
    }

    public function testMagicSetterAndMagicSetterMethodAndArrayAccessSetter() {

    }

    public function testMagicUnset() {

    }

    public function testDbRecordOldValue() {

    }

}
