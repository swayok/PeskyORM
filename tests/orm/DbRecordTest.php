<?php

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

    private function getDataForSingleAdmin($withIdAndHashedPassword = false, $withHashedPassword = false) {
        return array_merge($withIdAndHashedPassword ? ['id' => 1] : [], [
            'login' => '2AE351AF-131D-6654-9DB2-79B8F273986C',
            'password' => $withHashedPassword ? password_hash('KIS37QEG4HT', PASSWORD_DEFAULT) : 'KIS37QEG4HT',
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

    private function normalizeAdmin($adminData) {
        $adminData['is_superadmin'] = NormalizeValue::normalizeBoolean($adminData['is_superadmin']);
        $adminData['is_active'] = NormalizeValue::normalizeBoolean($adminData['is_active']);
        if ($adminData['parent_id'] !== null) {
            $adminData['parent_id'] = NormalizeValue::normalizeInteger($adminData['parent_id']);
        }
        if (array_key_exists('id', $adminData)) {
            $adminData['id'] = NormalizeValue::normalizeInteger($adminData['id']);
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
        $prop = $reflection->getMethod($methodName);
        $prop->setAccessible(true);
        return $prop->invokeArgs($object, $args);
    }

    public function testConstructor() {
        $rec1 = new TestingAdmin();
        $rec1->setValue('id', 1, false);
        static::assertTrue($rec1->hasValue('id', false));

        $rec2 = TestingAdmin::newEmptyRecord();
        static::assertInstanceOf(TestingAdmin::class, $rec2);
        static::assertFalse($rec2->hasValue('id', false));
        $rec2->setValue('id', 1, false);
        static::assertTrue($rec2->hasValue('id', false));

        $rec3 = TestingAdmin::_();
        static::assertInstanceOf(TestingAdmin::class, $rec3);
        static::assertFalse($rec3->hasValue('id', false));
    }

    public function testReset() {
        $rec = TestingAdmin::newEmptyRecord()
            ->setValue('id', 1, false)
            ->setValue('parent_id', 2, false);
        static::assertTrue($rec->hasValue('id', false));
        static::assertTrue($rec->hasValue('parent_id', false));
        $rec->next();
        static::assertEquals(1, $this->getObjectPropertyValue($rec, 'iteratorIdx'));
        $rec->updateValues(['Parent' => ['id' => 2, 'parent_id' => null]]);
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'relatedRecords'));
        /** @var DbRecordValue $valId1 */
        $valId1 = $this->callObjectMethod($rec, 'getValueObject', 'id');
        static::assertFalse($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        $rec->begin()->setValue('parent_id', 3, false);
        static::assertTrue($valId1->hasOldValue());
        static::assertEquals(2, $valId1->getOldValue());
        static::assertTrue($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        $rec->reset();
        static::assertFalse($rec->hasValue('id', false));
        static::assertFalse($rec->hasValue('parent_id', false));
        static::assertEquals(1, $valId1->getValue());
        static::assertEquals(0, $this->getObjectPropertyValue($rec, 'iteratorIdx'));
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'relatedRecords'));
        static::assertFalse($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        /** @var DbRecordValue $valId2 */
        $rec->setValue('id', 2, false);
        $valId2 = $this->callObjectMethod($rec, 'getValueObject', 'id');
        static::assertEquals(2, $valId2->getValue());
        static::assertFalse($valId2->hasOldValue());
        static::assertEquals($valId1->getValue(), $valId2->getOldValue());
        // reset value

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
        $rec->setValue('id', 2, false);
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

        $rec->setValue('id', 2, false);
        static::assertTrue($rec->hasValue('id', false));
        static::assertTrue($rec->hasValue('id', true));

        static::assertFalse($rec->hasValue('parent_id'));
        static::assertFalse($rec->hasValue('parent_id', false));
        static::assertFalse($rec->hasValue('parent_id', true));
    }

    /**
     * @expectedException \BadMethodCallException
     * @expectedExceptionMessage It is forbidden to modify or set value of a 'some_file' column
     */
    public function testInvalidSetValue() {
        $rec = new TestingAdmin();
        $rec::getColumn('some_file')->valueCannotBeSetOrChanged();
        $rec->setValue('some_file', 1, false);
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
        $rec->setValue('id', 3, false);
        static::assertTrue($rec->hasValue('id'));
        static::assertEquals(3, $rec->getValue('id'));
        /** @var DbRecordValue $val */
        $val = $this->callObjectMethod($rec, 'getValueObject', 'id');
        static::assertFalse($val->isItFromDb());
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

        static::assertTrue($rec->hasPrimaryKeyValue());
        static::assertEquals(4, $rec->getPrimaryKeyValue());
        $rec->reset();
        static::assertFalse($rec->hasPrimaryKeyValue());
    }

    public function testExistsInDb() {
        $this->insertMinimalTestDataToAdminsTable();

        $rec = new TestingAdmin();
        $prevQuery = TestingAdminsTable::getLastQuery();
        static::assertFalse($rec->existsInDb());
        static::assertFalse($rec->existsInDb(true));
        static::assertEquals($prevQuery, TestingAdminsTable::getLastQuery());

        $rec->setValue('id', 1, false);
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
                'timezone' => 'UTC'
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
                'some_file' => null
            ],
            $rec->getDefaults([], false, false)
        );
    }

    public function testToArray() {
        // toArray, toArrayWitoutFiles
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
                'timezone' => 'UTC'
            ],
            $rec->toArrayWitoutFiles()
        );

        $admin = $this->getDataForSingleAdmin(true);
        $adminNormalized = $this->normalizeAdmin($admin);
        $toArray = $rec->fromData($admin)->toArray();
        static::assertTrue(password_verify($admin['password'], $toArray['password']));
        unset($toArray['password'], $adminNormalized['password']);
        static::assertEquals(
            array_merge(['avatar' => 'not implemented', 'some_file' => 'not implemented'], $adminNormalized),
            $toArray
        );

        $adminNoId = $this->getDataForSingleAdmin(false);
        $adminNoIdNormalized = $this->normalizeAdmin($adminNoId);
        $toArray = $rec->fromData($adminNoId)->toArrayWitoutFiles();
        static::assertTrue(password_verify($adminNoId['password'], $toArray['password']));
        unset($toArray['password'], $adminNoIdNormalized['password']);
        static::assertEquals(array_merge(['id' => null], $adminNoIdNormalized), $toArray);
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
     * @expectedExceptionMessage error.invalid_data
     */
    public function testInvalidFromData3() {
        TestingAdmin::fromArray(['id' => 'qqqq']);
    }

    public function testFromData() {
        $adminWithId = $this->getDataForSingleAdmin(true);
        $normalizedAdminWithId = $this->normalizeAdmin($adminWithId);
        $adminWithoutId = $this->getDataForSingleAdmin(false);
        $normalizedAdminWithoutId = $this->normalizeAdmin($adminWithoutId);

        $rec = TestingAdmin::fromArray([]);
        static::assertEquals($rec->getDefaults([]), $rec->toArrayWitoutFiles());

        //$rec = TestingAdmin::fromArray($this->getDataForSingleAdmin(false), false);
        // fromArray, fromData ,fromDbData
    }

    public function testFromPrimaryKey() {
        // fromPrimaryKey, read
    }

    public function testFromDb() {
        // fromDb, find
    }

    public function testReadColumns() {

    }

    public function testSetRelatedRecord() {

    }

    public function testReadRelatedRecord() {

    }

    public function testGetAndHasRelatedRecord() {

    }

    public function testReload() {

    }

    public function testUpdateValues() {
        // merge, updateValues
    }

    public function testInvalidBegin1() {

    }

    public function testInvalidBegin2() {

    }

    public function testBegin() {

    }

    public function testInvalidRollback() {

    }

    public function testRollback() {

    }

    public function testInvalidCommit() {

    }

    public function testCommit() {

    }

    public function testGetAllColumnsWithUpdatableValues() {

    }

    public function testGetAllAutoUpdatingColumns() {

    }

    public function testInvalidSave() {

    }

    public function testInvalidSaveToDb1() {

    }

    public function testInvalidSaveToDb2() {

    }

    public function testSaveAndSaveToDbAndBeforeAfterSave() {
        // save, saveToDb, afterSave
    }

    public function testValidateNewData() {

    }

    public function testInvalidSaveRelations1() {

    }

    public function testInvalidSaveRelations2() {

    }

    public function testSaveRelations() {

    }

    public function testInvalidDelete() {

    }

    public function testDeleteAndBeforeAfterDelete() {

    }

    public function testIterationsAndArrayAccess() {

    }

    public function testMagicGetter() {

    }

    public function testMagicSetter() {

    }

    public function testMagicIsset() {

    }

    public function testMagicUnset() {

    }

    public function testMagicMethods() {

    }

}
