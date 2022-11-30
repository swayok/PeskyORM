<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\DbExpr;
use PeskyORM\Exception\InvalidDataException;
use PeskyORM\Exception\RecordNotFoundException;
use PeskyORM\ORM\Record\Record;
use PeskyORM\ORM\Record\RecordValue;
use PeskyORM\ORM\RecordsCollection\RecordsSet;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin2;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin3;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use PeskyORM\Tests\PeskyORMTest\TestingSettings\TestingSetting;
use PeskyORM\Tests\PeskyORMTest\TestingSettings\TestingSettingsTable;
use PeskyORM\Tests\PeskyORMTest\TestingSettings\TestingSettingsTableStructure;
use PeskyORM\Utils\StringUtils;
use Swayok\Utils\NormalizeValue;
use Swayok\Utils\Set;

class RecordTest extends BaseTestCase
{
    
    public static function tearDownAfterClass(): void
    {
        TestingApp::clearTables(TestingAdminsTable::getConnection());
        parent::tearDownAfterClass();
    }
    
    protected function setUp(): void
    {
        TestingApp::clearTables(TestingAdminsTable::getConnection());
        TestingApp::cleanInstancesOfDbTablesAndRecordsAndStructures();
    }
    
    public static function fillAdminsTable(int $limit = 0): array
    {
        TestingAdminsTable::getConnection(true)->exec('TRUNCATE TABLE admins');
        $data = TestingApp::getRecordsForDb('admins', $limit);
        // avoid using TestingAdminsTable::insertMany()
        // to avoid autoupdatable columns usage *updated_at for example
        TestingAdminsTable::getConnection()->insertMany('admins', array_keys($data[0]), $data);
        return $data;
    }
    
    private function insertMinimalTestDataToAdminsTable(): void
    {
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
                'timezone' => 'Europe/Moscow',
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
                'timezone' => 'Europe/Moscow',
            ],
        ];
        TestingAdminsTable::getConnection(true)->insertMany('admins', array_keys($data[0]), $data);
    }
    
    private function getDataForSingleAdmin($withId = false): array
    {
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
            'timezone' => 'Europe/Moscow',
            'big_data' => 'biiiig data',
        ]);
    }
    
    private function normalizeAdmin($adminData, $addNotChangeableCol = true, $addNotExistingCol = true)
    {
        $adminData['is_superadmin'] = NormalizeValue::normalizeBoolean($adminData['is_superadmin']);
        $adminData['is_active'] = NormalizeValue::normalizeBoolean($adminData['is_active']);
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
            $adminData['not_existing_column_with_default_value'] = 'default';
            $adminData['not_existing_column_with_calculated_value'] = 'calculated-' . $adminData['id'];
        }
        unset($adminData['password']);
        return $adminData;
    }
    
    public function testConstructor(): void
    {
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
    
    public function testReset(): void
    {
        $rec = TestingAdmin::newEmptyRecord()
            ->updateValue('id', 1, true)
            ->updateValue('parent_id', 2, true);
        static::assertTrue($rec->hasValue('id', false));
        static::assertTrue($rec->hasValue('parent_id', false));
        $rec->next();
        static::assertEquals(1, $this->getObjectPropertyValue($rec, 'iteratorIdx'));
        $rec->updateValues(['Parent' => ['id' => 2, 'parent_id' => null]], true);
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'relatedRecords'));
        /** @var RecordValue $valId1 */
        $valId1 = $this->callObjectMethod($rec, 'getValueContainer', 'id');
        static::assertFalse($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        $rec->begin()
            ->updateValue('parent_id', 3, false);
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
        /** @var RecordValue $valId2 */
        $valId2 = $this->callObjectMethod($rec, 'getValueContainer', 'id');
        $rec->updateValue('id', 2, true);
        /** @var RecordValue $valId3 */
        $valId3 = $this->callObjectMethod($rec, 'getValueContainer', 'id');
        static::assertEquals(2, $valId3->getValue());
    }
    
    public function testStaticMethods(): void
    {
        // tables and structures
        static::assertInstanceOf(TestingSetting::class, TestingSetting::_());
        static::assertInstanceOf(TestingSetting::class, TestingSetting::newEmptyRecord());
        static::assertInstanceOf(TestingSettingsTable::class, TestingSetting::getTable());
        static::assertSame(TestingSettingsTable::getInstance(), TestingSetting::getTable());
        static::assertInstanceOf(TestingSettingsTableStructure::class, TestingSetting::getTableStructure());
        static::assertSame(TestingSettingsTableStructure::getInstance(), TestingSetting::getTableStructure());
        
        static::assertInstanceOf(TestingAdmin::class, TestingAdmin::_());
        static::assertInstanceOf(TestingAdmin::class, TestingAdmin::newEmptyRecord());
        static::assertInstanceOf(TestingAdminsTable::class, TestingAdmin::getTable());
        static::assertSame(TestingAdminsTable::getInstance(), TestingAdmin::getTable());
        static::assertInstanceOf(TestingAdminsTableStructure::class, TestingAdmin::getTableStructure());
        static::assertSame(TestingAdminsTableStructure::getInstance(), TestingAdmin::getTableStructure());
        
        // columns
        static::assertSame(TestingAdminsTableStructure::getColumns(), TestingAdmin::getColumns());
        static::assertTrue(TestingAdmin::hasColumn('language'));
        static::assertSame(
            TestingAdminsTableStructure::getColumn('language'),
            TestingAdmin::getColumn('language')
        );
        static::assertSame(
            TestingAdminsTableStructure::getColumn('parent_id'),
            TestingAdmin::getColumn('parent_id')
        );
        static::assertSame(
            TestingAdminsTableStructure::getColumn('id'),
            TestingAdmin::getPrimaryKeyColumn()
        );
        static::assertEquals('id', TestingAdmin::getPrimaryKeyColumnName());
        
        static::assertSame(TestingSettingsTableStructure::getColumns(), TestingSetting::getColumns());
        static::assertFalse(TestingSetting::hasColumn('language'));
        static::assertTrue(TestingSetting::hasColumn('key'));
        static::assertSame(
            TestingSettingsTableStructure::getColumn('key'),
            TestingSetting::getColumn('key')
        );
        static::assertSame(
            TestingSettingsTableStructure::getColumn('value'),
            TestingSetting::getColumn('value')
        );
        static::assertSame(
            TestingSettingsTableStructure::getColumn('id'),
            TestingSetting::getPrimaryKeyColumn()
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
        
        // validate value
        static::assertEquals([], TestingAdmin::validateValue('language', 'ru', true));
        static::assertEquals([], TestingAdmin::validateValue('language', 'ru', false));
        static::assertEquals([], TestingAdmin::validateValue('language', 'qq', true));
        static::assertEquals([], TestingAdmin::validateValue('language', 'qq', false));
        
        // columns that exist in db or not
        $expectedNotExistingColumnsList = [
            'avatar',
            'some_file',
            'not_existing_column',
            'not_existing_column_with_default_value',
            'not_existing_column_with_calculated_value',
        ];
        static::assertEquals($expectedNotExistingColumnsList, array_keys(TestingAdmin::getColumnsThatDoNotExistInDb()));
        static::assertEquals([], array_keys(TestingSetting::getColumnsThatDoNotExistInDb()));
        static::assertEquals(
            [
                'id',
                'parent_id',
                'login',
                'password',
                'created_at',
                'updated_at',
                'remember_token',
                'is_superadmin',
                'language',
                'ip',
                'role',
                'is_active',
                'name',
                'email',
                'timezone',
                'not_changeable_column',
                'big_data',
            ],
            array_keys(TestingAdmin::getColumnsThatExistInDb())
        );
        static::assertEquals(['id', 'key', 'value'], array_keys(TestingSetting::getColumnsThatExistInDb()));
    }
    
    public function testCreateDbValueObject(): void
    {
        $rec = TestingAdmin::newEmptyRecord();
        $this->callObjectMethod($rec, 'createValueObject', $rec::getColumn('id'));
        static::assertInstanceOf(RecordValue::class, $this->callObjectMethod($rec, 'getValueContainer', 'id'));
        static::assertInstanceOf(RecordValue::class, $this->callObjectMethod($rec, 'getValueContainer', $rec::getColumn('id')));
    }
    
    public function testResetValue(): void
    {
        $rec = TestingAdmin::newEmptyRecord();
        $rec->updateValue('id', 1, true);
        static::assertEquals(1, $rec->getValue('id'));
        $this->callObjectMethod($rec, 'resetValue', 'id');
        static::assertFalse($rec->hasValue('id', false));
        $rec->updateValue('id', 1, true);
        static::assertEquals(1, $rec->getValue('id'));
        $this->callObjectMethod($rec, 'resetValue', $rec::getColumn('id'));
        static::assertFalse($rec->hasValue('id', false));
    }
    
    public function testCleanUpdates(): void
    {
        $rec = TestingAdmin::newEmptyRecord();
        $rec->updateValue('id', 1, true)
            ->begin()
            ->updateValue('parent_id', 2, false);
        static::assertTrue($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        $this->callObjectMethod($rec, 'cleanUpdates');
        static::assertFalse($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
    }
    
    public function testGetValueContainer(): void
    {
        $rec = TestingAdmin::newEmptyRecord();
        static::assertArrayNotHasKey('id', $this->getObjectPropertyValue($rec, 'values')); //< lazy load
        $container1 = $this->callObjectMethod($rec, 'getValueContainer', 'id');
        static::assertEquals(
            $this->getObjectPropertyValue($rec, 'values')['id'],
            $container1
        );
        static::assertArrayNotHasKey('parent_id', $this->getObjectPropertyValue($rec, 'values')); //< lazy load
        $container2 = $this->callObjectMethod($rec, 'getValueContainer', $rec::getColumn('parent_id'));
        static::assertEquals(
            $this->getObjectPropertyValue($rec, 'values')['parent_id'],
            $container2
        );
    }
    
    public function testInvalidGetValue1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessageMatches(
            "%Value for .*?\->id is not set and default value is not provided%"
        );
        $rec = TestingAdmin::newEmptyRecord();
        $rec->getValue('id');
    }

    public function testInvalidGetValue2(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessageMatches(
            "%Value for .*?\(#1\)->is_superadmin is not set and default value cannot be used%"
        );
        $rec = TestingAdmin::newEmptyRecord();
        $rec->setId(1, true);
        $rec->getValue('is_superadmin');
    }

    public function testGetValue(): void
    {
        $rec = TestingAdmin::newEmptyRecord();
        static::assertEquals(
            TestingAdmin::getColumn('is_superadmin')->getDefaultValue(),
            $rec->getValue('is_superadmin')
        );
        $rec->updateValue('is_superadmin', true, false);
        static::assertTrue($rec->getValue('is_superadmin'));
        static::assertTrue($rec->getValue(TestingAdmin::getColumn('is_superadmin')));
    }
    
    public function testHasValueOrDefaultValue(): void
    {
        $rec = new TestingAdmin();
        static::assertFalse($rec->hasValue('id'));
        static::assertFalse($rec->hasValue('id', false));
        static::assertFalse($rec->hasValue('id', true));

        static::assertFalse($rec->hasValue('is_superadmin'));
        static::assertFalse($rec->hasValue('is_superadmin', false));
        static::assertTrue($rec->hasValue('is_superadmin', true));
        
        $rec->updateValue('id', 2, true);
        static::assertTrue($rec->hasValue('id', false));
        static::assertTrue($rec->hasValue('id', true));
        
        static::assertFalse($rec->hasValue('parent_id'));
        static::assertFalse($rec->hasValue('parent_id', false));
        static::assertFalse($rec->hasValue('parent_id', true));

        static::assertFalse($rec->hasValue('is_superadmin'));
        static::assertFalse($rec->hasValue('is_superadmin', false));
        // record has pk value so default value cannot be used
        static::assertFalse($rec->hasValue('is_superadmin', true));
    }
    
    public function testInvalidSetValue1(): void
    {
        $this->expectExceptionMessage("It is forbidden to modify or set value of a 'not_changeable_column' column");
        $this->expectException(\BadMethodCallException::class);
        $rec = new TestingAdmin();
        $rec->updateValue('not_changeable_column', 1, false);
    }
    
    public function testInvalidSetValue2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Attempt to set a value for column [parent_id] with flag \$isFromDb === true while record does not exist in DB"
        );
        $rec = new TestingAdmin();
        $rec->updateValue('parent_id', 1, true);
    }
    
    public function testInvalidSetValue3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Attempt to set a value for column [parent_id] with flag \$isFromDb === true while record does not exist in DB"
        );
        $rec = new TestingAdmin();
        $rec->updateValue('parent_id', 1, true);
    }
    
    public function testInvalidSetValue4(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("It is forbidden to set value with \$isFromDb === true after begin()");
        $rec = new TestingAdmin();
        $rec->updateValue('id', 1, true);
        $rec->begin()
            ->updateValue('parent_id', 2, true);
    }
    
    public function testInvalidSetPkValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("It is forbidden to change primary key value when \$isFromDb === false");
        $rec = new TestingAdmin();
        $rec->updateValue('id', 1, false);
    }
    
    public function testInvalidUnsetValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column 'invalidcolumn' in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        $rec = new TestingAdmin();
        $rec->unsetValue('invalidcolumn');
    }
    
    public function testSetValueAndSetPkValueAndUnsetValue(): void
    {
        $rec = new TestingAdmin();
        static::assertFalse($rec->hasValue('id'));
        $rec->updateValue('id', 2, true);
        static::assertTrue($rec->hasValue('id'));
        static::assertEquals(2, $rec->getValue('id'));
        /** @var RecordValue $val */
        $val = $this->callObjectMethod($rec, 'getValueContainer', 'id');
        static::assertTrue($val->isItFromDb());
        $rec->updateValue('id', 3, true);
        static::assertTrue($rec->hasValue('id'));
        static::assertEquals(3, $rec->getValue('id'));
        /** @var RecordValue $val */
        $val = $this->callObjectMethod($rec, 'getValueContainer', 'id');
        static::assertTrue($val->isItFromDb());

        $rec->updateValue('id', 4, true)
            ->begin()
            ->updateValue('parent_id', 3, false);
        static::assertTrue($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('parent_id', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertNotEquals(
            $this->getObjectPropertyValue($rec, 'valuesBackup')['parent_id'],
            $this->callObjectMethod($rec, 'getValueContainer', 'parent_id')
        );
        $rec->rollback();
        
        static::assertTrue($rec->hasPrimaryKeyValue());
        static::assertEquals(4, $rec->getPrimaryKeyValue());
        $rec->reset();
        static::assertFalse($rec->hasPrimaryKeyValue());
        
        $rec
            ->reset()
            ->updateValue('id', 1, true)
            ->updateValue('parent_id', 4, true)
            ->updateValue('email', 'test@test.cc', true);
        static::assertTrue($rec->hasPrimaryKeyValue());
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertTrue($rec->isValueFromDb('email'));
        $rec->updateValue('id', 2, true);
        static::assertTrue($rec->hasPrimaryKeyValue());
        static::assertEquals(2, $rec->getValue('id'));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertEquals(4, $rec->getValue('parent_id'));
        static::assertFalse($rec->isValueFromDb('parent_id'));
        static::assertEquals('test@test.cc', $rec->getValue('email'));
        static::assertFalse($rec->isValueFromDb('email'));
        
        $rec
            ->reset()
            ->updateValue('id', 1, true)
            ->updateValue('parent_id', 4, true)
            ->updateValue('email', 'test@test.cc', true);
        $rec->unsetPrimaryKeyValue();
        static::assertFalse($rec->hasPrimaryKeyValue());
        static::assertEquals(4, $rec->getValue('parent_id'));
        static::assertFalse($rec->isValueFromDb('parent_id'));
        static::assertEquals('test@test.cc', $rec->getValue('email'));
        static::assertFalse($rec->isValueFromDb('email'));
        
        $rec
            ->reset()
            ->updateValue('id', 1, true)
            ->updateValue('parent_id', 4, true)
            ->updateValue('email', 'test@test.cc', true);
        static::assertEquals(4, $rec->getValue('parent_id'));
        $rec->unsetValue('parent_id');
        static::assertFalse($rec->hasValue('parent_id'));
        static::assertEquals('test@test.cc', $rec->getValue('email'));
    }
    
    public function testExistsInDb(): void
    {
        $this->insertMinimalTestDataToAdminsTable();
        
        $rec = new TestingAdmin();
        $prevQuery = TestingAdminsTable::getLastQuery(false);
        static::assertFalse($rec->existsInDb());
        static::assertFalse($rec->existsInDb(true));
        static::assertEquals($prevQuery, TestingAdminsTable::getLastQuery(false));
        
        $rec->updateValue('id', 1, true);
        $prevQuery = TestingAdminsTable::getLastQuery(false);
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertNotEquals($prevQuery, TestingAdminsTable::getLastQuery(false));
        
        $rec->updateValue('id', 888, true);
        $prevQuery = TestingAdminsTable::getLastQuery(false);
        static::assertTrue($rec->existsInDb());
        static::assertFalse($rec->existsInDb(true));
        static::assertNotEquals($prevQuery, TestingAdminsTable::getLastQuery(false));
    }
    
    public function testGetDefaults(): void
    {
        $rec = TestingAdmin::newEmptyRecord();
        static::assertEquals(
            [
                'id' => null,
                'parent_id' => null,
                'login' => null,
                'password' => null,
                'created_at' => null,
                'remember_token' => null,
                'is_superadmin' => false,
                'language' => 'en',
                'ip' => null,
                'role' => 'guest',
                'is_active' => true,
                'name' => '',
                'email' => null,
                'timezone' => 'UTC',
                'big_data' => 'this is big data value! really! I\'m not joking!',
            ],
            $rec->getDefaults()
        );
        static::assertEquals(
            [
                'id' => null,
                'parent_id' => null,
                'login' => null,
                'password' => null,
                'created_at' => TestingAdminsTableStructure::getColumn('created_at')
                    ->getDefaultValue(),
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
                'not_existing_column_with_default_value' => 'default',
                'not_existing_column_with_calculated_value' => null,
                'big_data' => 'this is big data value! really! I\'m not joking!',
            ],
            $rec->getDefaults([], false, false)
        );
    }
    
    public function testInvalidRelationRequestInToArray1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Related record with name 'Parent' is not set and autoloading is disabled");
        TestingAdmin::fromArray(['id' => 1], true)
            ->toArrayWithoutFiles(['id'], ['Parent']);
    }
    
    public function testInvalidRelationRequestInToArray1Alt(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Related record with name 'Parent' is not set and autoloading is disabled");
        TestingAdmin::fromArray(['id' => 1], true)
            ->toArrayWithoutFiles(['id', 'Parent']);
    }
    
    public function testInvalidRelationRequestInToArray2(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Related record with name 'Children' is not set and autoloading is disabled");
        TestingAdmin::fromArray(['id' => 1], true)
            ->toArrayWithoutFiles(['id'], ['Children']);
    }
    
    public function testInvalidRelationRequestInToArray2Alt(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Related record with name 'Children' is not set and autoloading is disabled");
        TestingAdmin::fromArray(['id' => 1], true)
            ->toArrayWithoutFiles(['id', 'Children']);
    }
    
    public function testInvalidRelationRequestInToArray3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no relation 'Invalid' in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        TestingAdmin::fromArray(['id' => 1], true)
            ->toArrayWithoutFiles(['id'], ['Invalid']);
    }
    
    public function testInvalidRelationRequestInToArray3Alt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column 'Invalid' in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        TestingAdmin::fromArray(['id' => 1], true)
            ->toArrayWithoutFiles(['id', 'Invalid']);
    }
    
    /**
     * @covers Record::getColumnValueForToArray()
     */
    public function testGetColumnValueForToArray(): void
    {
        $rec = TestingAdmin::fromArray(['parent_id' => 1], false);
        static::assertEquals(null, $rec->getColumnValueForToArray('id'));
        static::assertEquals(1, $rec->getColumnValueForToArray('parent_id'));
        static::assertEquals('en', $rec->getColumnValueForToArray('language'));
        static::assertEquals(null, $rec->getColumnValueForToArray('avatar'));
        $rec->updateValue('id', 2, true);
        static::assertEquals(2, $rec->getColumnValueForToArray('id'));
        static::assertEquals(1, $rec->getColumnValueForToArray('parent_id'));
        static::assertEquals(null, $rec->getColumnValueForToArray('language'));
        static::assertEquals(null, $rec->getColumnValueForToArray('avatar'));
    }
    
    /**
     * @covers Record::toArray()
     * @covers Record::toArrayWithoutFiles()
     */
    public function testToArray1(): void
    {
        // toArray, toArrayWithoutFiles
        $rec = TestingAdmin::fromArray([]);
        static::assertEquals(
            [
                'id' => null,
                'parent_id' => null,
                'login' => null,
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
                'not_existing_column' => null,
                'not_existing_column_with_default_value' => 'default',
                'not_existing_column_with_calculated_value' => 'calculated-',
                'big_data' => 'this is big data value! really! I\'m not joking!',
            ],
            $rec->toArrayWithoutFiles()
        );
    
        static::assertEquals(
            [
                'password' => null
            ],
            $rec->toArrayWithoutFiles(['password'])
        );
        
        $admin = $this->getDataForSingleAdmin(true);
        $adminNormalized = $this->normalizeAdmin($admin, null);
        // get all columns
        $toArray = $rec->fromData($admin, true)
            ->toArray();
        $notExpectedColumns = [
            'not_changeable_column',
            'not_existing_column',
            'password',
            'not_existing_column_with_default_value',
        ];
        static::assertEquals(array_diff_key($adminNormalized, array_flip($notExpectedColumns)), $toArray);
        
        // get only several columns
        $toArrayPartial = $rec->toArray(['id', 'parent_id', 'login', 'role']);
        static::assertEquals(array_intersect_key($adminNormalized, $toArrayPartial), $toArrayPartial);
        
        // column exclusion from wildcard (string)
        $toArrayPartial = $rec->fromData($admin, true)
            ->toArray(['*' => 'big_data']);
        $notExpectedColumns[] = 'big_data';
        static::assertEquals(array_diff_key($adminNormalized, array_flip($notExpectedColumns)), $toArrayPartial);
        
        // column exclusion from wildcard (array)
        $toArrayPartial = $rec->fromData($admin, true)
            ->toArray(['*' => ['big_data', 'language']]);
        $notExpectedColumns[] = 'language';
        static::assertEquals(array_diff_key($adminNormalized, array_flip($notExpectedColumns)), $toArrayPartial);
        
        // update not_existing_column_with_default_value and see if it will be in resulting array
        $rec->fromData($admin, true);
        $rec->updateValue('not_existing_column_with_default_value', 'custom', true);
        $adminNormalized['not_existing_column_with_default_value'] = 'custom';
        $toArray = $rec->toArray();
        $notExpectedColumns = [
            'not_changeable_column',
            'not_existing_column',
            'password',
        ];
        static::assertEquals(array_diff_key($adminNormalized, array_flip($notExpectedColumns)), $toArray);
    }
    
    /**
     * @covers Record::toArray()
     * @covers Record::toArrayWithoutFiles()
     */
    public function testToArray2(): void
    {
        $rec = TestingAdmin::new1();
        $adminNoId = $this->getDataForSingleAdmin(false);
        $adminNoIdNormalized = $this->normalizeAdmin($adminNoId, null);
        
        $toArray = $rec->fromData($adminNoId)
            ->toArrayWithoutFiles();
        $toArrayPartial = $rec->toArrayWithoutFiles(['id', 'parent_id', 'login', 'role']);
        $expected = array_merge(['id' => null], $adminNoIdNormalized);
        static::assertEquals($expected, $toArray);
        $expected = array_intersect_key($expected, $toArrayPartial);
        static::assertEquals($expected, $toArrayPartial);
        // using column alias
        $toArrayPartial = $rec->toArrayWithoutFiles(['id', 'parent_id', 'login' => 'alias', 'role']);
        $expected['alias'] = $expected['login'];
        unset($expected['login']);
        static::assertEquals(array_merge(['id' => null], $expected), $toArrayPartial);
        
        // has one / belongs to relations (not existing in db)
        $rec->updateRelatedRecord('Parent', [], false);
        $toArrayRelation = $rec->toArrayWithoutFiles(['id'], ['Parent']);
        static::assertEquals(['id' => null], $toArrayRelation);
        
        $toArrayRelation = $rec->toArrayWithoutFiles(['id', 'Parent']);
        static::assertEquals(['id' => null], $toArrayRelation);
        
        $adminNoId['Parent'] = $adminNoId;
        $expected = ['id' => null, 'Parent' => array_merge(['id' => null], $adminNoIdNormalized)];
        $toArrayRelation = $rec->fromData($adminNoId)
            ->toArrayWithoutFiles(['id'], ['Parent']);
        static::assertEquals($expected, $toArrayRelation);
        
        $toArrayRelation = $rec->fromData($adminNoId)
            ->toArrayWithoutFiles(['id', 'Parent']);
        static::assertEquals($expected, $toArrayRelation);
        
        // has one / belongs to relations (existing in db)
        $insertedRecords = static::fillAdminsTable(10);
        unset($adminNoId['Parent'], $insertedRecords[0]['password'], $insertedRecords[1]['password'], $insertedRecords[2]['password']);
        $expected = ['id' => $insertedRecords[1]['id'], 'Parent' => $insertedRecords[0]];
        $expected['Parent']['created_at'] .= '+00';
        $expected['Parent']['updated_at'] .= '+00';
        $expected['Parent']['not_existing_column_with_calculated_value'] = 'calculated-' . $expected['Parent']['id'];
        unset($expected['Parent']['big_data']); //< heavy so it won't be fetched
        $toArrayRelation = $rec->fetchByPrimaryKey($insertedRecords[1]['id'])
            ->toArrayWithoutFiles(['id'], ['Parent'], true);
        static::assertEquals($expected, $toArrayRelation);
        
        $toArrayRelation = $rec->fetchByPrimaryKey($insertedRecords[1]['id'])
            ->toArrayWithoutFiles(['id', 'Parent'], [], true);
        static::assertEquals($expected, $toArrayRelation);
        
        $expected = ['id' => $insertedRecords[1]['id'], 'Parent' => ['login' => $insertedRecords[0]['login']]];
        $toArrayRelation = $rec->fetchByPrimaryKey($insertedRecords[1]['id'])
            ->toArrayWithoutFiles(['id'], ['Parent' => ['login']], true);
        static::assertEquals($expected, $toArrayRelation);
        
        $expected = ['id' => $insertedRecords[1]['id'], 'Parent' => ['alias' => $insertedRecords[0]['login']]];
        $toArrayRelation = $rec->fetchByPrimaryKey($insertedRecords[1]['id'])
            ->toArrayWithoutFiles(['id'], ['Parent' => ['login' => 'alias']], true);
        static::assertEquals($expected, $toArrayRelation);
        $toArrayRelation = $rec->fetchByPrimaryKey($insertedRecords[1]['id'])
            ->toArrayWithoutFiles(['id', 'Parent' => ['login' => 'alias']], [], true);
        static::assertEquals($expected, $toArrayRelation);
        
        // has many relations
        $expected = ['id' => $insertedRecords[0]['id'], 'Children' => [$insertedRecords[1], $insertedRecords[2]]];
        $expected['Children'][0]['created_at'] .= '+00';
        $expected['Children'][0]['updated_at'] .= '+00';
        $expected['Children'][0]['not_existing_column_with_calculated_value'] = 'calculated-' . $expected['Children'][0]['id'];
        unset($expected['Children'][0]['big_data']);
        $expected['Children'][1]['created_at'] .= '+00';
        $expected['Children'][1]['updated_at'] .= '+00';
        $expected['Children'][1]['not_existing_column_with_calculated_value'] = 'calculated-' . $expected['Children'][1]['id'];
        unset($expected['Children'][1]['big_data']);
        $toArrayRelation = $rec->fetchByPrimaryKey($insertedRecords[0]['id'])
            ->toArrayWithoutFiles(['id'], ['Children'], true);
        static::assertEquals($expected, $toArrayRelation);
        $toArrayRelation = $rec->fetchByPrimaryKey($insertedRecords[0]['id'])
            ->toArrayWithoutFiles(['id', 'Children'], [], true);
        static::assertEquals($expected, $toArrayRelation);
        
        $expected = [
            'id' => $insertedRecords[0]['id'],
            'Children' => [
                ['email' => $insertedRecords[1]['email']],
                ['email' => $insertedRecords[2]['email']],
            ],
        ];
        $toArrayRelation = $rec->fetchByPrimaryKey($insertedRecords[0]['id'])
            ->toArrayWithoutFiles(['id'], ['Children' => ['email']], true);
        static::assertEquals($expected, $toArrayRelation);
        $toArrayRelation = $rec->fetchByPrimaryKey($insertedRecords[0]['id'])
            ->toArrayWithoutFiles(['id', 'Children' => ['email']], [], true);
        static::assertEquals($expected, $toArrayRelation);
        
        $expected = [
            'id' => $insertedRecords[0]['id'],
            'Children' => [
                ['alias' => $insertedRecords[1]['email']],
                ['alias' => $insertedRecords[2]['email']],
            ],
        ];
        $toArrayRelation = $rec->fetchByPrimaryKey($insertedRecords[0]['id'])
            ->toArrayWithoutFiles(['id'], ['Children' => ['email' => 'alias']], true);
        static::assertEquals($expected, $toArrayRelation);
        $toArrayRelation = $rec->fetchByPrimaryKey($insertedRecords[0]['id'])
            ->toArrayWithoutFiles(['id', 'Children' => ['email' => 'alias']], [], true);
        static::assertEquals($expected, $toArrayRelation);
    }
    
    public function testToArray3(): void
    {
        $rec = TestingAdmin::new1();
        $admin = $this->getDataForSingleAdmin(true);
        $admin['password'] = password_hash('password', PASSWORD_BCRYPT);
        $admin['Parent'] = $admin;
        $admin['Parent']['id'] = $admin['id'] + 1;
        $admin['Parent']['parent_id'] = $admin['id'];
    
        $toArray = $rec->fromData($admin, true)
            ->toArrayWithoutFiles([
                'email' => function ($value) {
                    return $value;
                },
                'login' => function () {
                    return 'fake_login';
                },
                'password' => function ($value) {
                    return $value;
                },
                'not_existing' => function (TestingAdmin $record) {
                    return $record->login;
                },
                'Parent' => [
                    'id',
                    'parent_id',
                    'password' => function () {
                        return null;
                    },
                    'not_existing' => function (TestingAdmin $record) {
                        return $record->parent_id;
                    },
                ]
            ]);
        $expected = [
            'email' => $admin['email'],
            'login' => 'fake_login',
            'password' => $admin['password'],
            'not_existing' => $admin['login'],
            'Parent' => [
                'id' => $admin['Parent']['id'],
                'parent_id' => $admin['Parent']['parent_id'],
                'password' => null,
                'not_existing' => $admin['Parent']['parent_id'],
            ]
        ];
        static::assertEquals($expected, $toArray);
    }
    
    /**
     * @covers \PeskyORM\ORM\Record\Record::serialize()
     * @covers Record::unserialize()
     */
    public function testSerialization(): void
    {
        $rec = TestingAdmin::fromArray($this->getDataForSingleAdmin(true), true);
        $recSerialized = serialize($rec);
        static::assertEquals(
            'C:54:"PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin":1838:{{"props":{"existsInDb":true},"values":{"id":{"rawValue":null,"ignoreRawValue":true,"value":1,"hasValue":true,"isFromDb":true,"payload":[]},"login":{"rawValue":null,"ignoreRawValue":true,"value":"2AE351AF-131D-6654-9DB2-79B8F273986C","hasValue":true,"isFromDb":true,"payload":[]},"parent_id":{"rawValue":null,"ignoreRawValue":true,"value":1,"hasValue":true,"isFromDb":true,"payload":[]},"created_at":{"rawValue":null,"ignoreRawValue":true,"value":"2015-05-14 02:12:05","hasValue":true,"isFromDb":true,"payload":[]},"updated_at":{"rawValue":null,"ignoreRawValue":true,"value":"2015-06-10 19:30:24","hasValue":true,"isFromDb":true,"payload":[]},"remember_token":{"rawValue":null,"ignoreRawValue":true,"value":"6A758CB2-234F-F7A1-24FE-4FE263E6FF81","hasValue":true,"isFromDb":true,"payload":[]},"is_superadmin":{"rawValue":null,"ignoreRawValue":true,"value":true,"hasValue":true,"isFromDb":true,"payload":[]},"language":{"rawValue":null,"ignoreRawValue":true,"value":"en","hasValue":true,"isFromDb":true,"payload":[]},"ip":{"rawValue":null,"ignoreRawValue":true,"value":"192.168.0.1","hasValue":true,"isFromDb":true,"payload":[]},"role":{"rawValue":null,"ignoreRawValue":true,"value":"admin","hasValue":true,"isFromDb":true,"payload":[]},"is_active":{"rawValue":"1","ignoreRawValue":false,"value":true,"hasValue":true,"isFromDb":true,"payload":[]},"name":{"rawValue":null,"ignoreRawValue":true,"value":"Lionel Freeman","hasValue":true,"isFromDb":true,"payload":[]},"email":{"rawValue":null,"ignoreRawValue":true,"value":"diam.at.pretium@idmollisnec.co.uk","hasValue":true,"isFromDb":true,"payload":[]},"timezone":{"rawValue":null,"ignoreRawValue":true,"value":"Europe\/Moscow","hasValue":true,"isFromDb":true,"payload":[]},"big_data":{"rawValue":null,"ignoreRawValue":true,"value":"biiiig data","hasValue":true,"isFromDb":true,"payload":[]}}}}',
            $recSerialized
        );
        /** @var TestingAdmin $recUnserialized */
        $recUnserialized = unserialize($recSerialized);
        static::assertInstanceOf(Record::class, $recUnserialized);
        static::assertEquals($rec, $recUnserialized);
        static::assertEquals($this->getObjectPropertyValue($rec, 'values'), $this->getObjectPropertyValue($recUnserialized, 'values'));
        static::assertEquals($rec->toArrayWithoutFiles(), $recUnserialized->toArrayWithoutFiles());
        static::assertEquals($rec->isValueFromDb('parent_id'), $recUnserialized->isValueFromDb('parent_id'));
        static::assertEquals($rec->existsInDb(), $recUnserialized->existsInDb());
        
        $rec->fromData($this->getDataForSingleAdmin(false), false);
        $recSerialized = serialize($rec);
        static::assertEquals(
            'C:54:"PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin":1752:{{"props":{"existsInDb":null},"values":{"login":{"rawValue":null,"ignoreRawValue":true,"value":"2AE351AF-131D-6654-9DB2-79B8F273986C","hasValue":true,"isFromDb":false,"payload":[]},"parent_id":{"rawValue":null,"ignoreRawValue":true,"value":1,"hasValue":true,"isFromDb":false,"payload":[]},"created_at":{"rawValue":null,"ignoreRawValue":true,"value":"2015-05-14 02:12:05","hasValue":true,"isFromDb":false,"payload":[]},"updated_at":{"rawValue":null,"ignoreRawValue":true,"value":"2015-06-10 19:30:24","hasValue":true,"isFromDb":false,"payload":[]},"remember_token":{"rawValue":null,"ignoreRawValue":true,"value":"6A758CB2-234F-F7A1-24FE-4FE263E6FF81","hasValue":true,"isFromDb":false,"payload":[]},"is_superadmin":{"rawValue":null,"ignoreRawValue":true,"value":true,"hasValue":true,"isFromDb":false,"payload":[]},"language":{"rawValue":null,"ignoreRawValue":true,"value":"en","hasValue":true,"isFromDb":false,"payload":[]},"ip":{"rawValue":null,"ignoreRawValue":true,"value":"192.168.0.1","hasValue":true,"isFromDb":false,"payload":[]},"role":{"rawValue":null,"ignoreRawValue":true,"value":"admin","hasValue":true,"isFromDb":false,"payload":[]},"is_active":{"rawValue":"1","ignoreRawValue":false,"value":true,"hasValue":true,"isFromDb":false,"payload":[]},"name":{"rawValue":null,"ignoreRawValue":true,"value":"Lionel Freeman","hasValue":true,"isFromDb":false,"payload":[]},"email":{"rawValue":null,"ignoreRawValue":true,"value":"diam.at.pretium@idmollisnec.co.uk","hasValue":true,"isFromDb":false,"payload":[]},"timezone":{"rawValue":null,"ignoreRawValue":true,"value":"Europe\/Moscow","hasValue":true,"isFromDb":false,"payload":[]},"big_data":{"rawValue":null,"ignoreRawValue":true,"value":"biiiig data","hasValue":true,"isFromDb":false,"payload":[]}}}}',
            $recSerialized
        );
        /** @var TestingAdmin $recUnserialized */
        $recUnserialized = unserialize($recSerialized);
        static::assertInstanceOf(Record::class, $recUnserialized);
        static::assertEquals($rec->existsInDb(), $recUnserialized->existsInDb());
        static::assertEquals($rec, $recUnserialized);
        static::assertEquals($this->getObjectPropertyValue($rec, 'values'), $this->getObjectPropertyValue($recUnserialized, 'values'));
        static::assertEquals($rec->toArrayWithoutFiles(), $recUnserialized->toArrayWithoutFiles());
        static::assertEquals($rec->isValueFromDb('parent_id'), $recUnserialized->isValueFromDb('parent_id'));
    }
    
    public function testIsValueFromDb(): void
    {
        $rec = TestingAdmin::newEmptyRecord();
        $rec->updateValue('parent_id', 1, false);
        static::assertFalse($rec->isValueFromDb('parent_id'));
        $rec
            ->updateValue('id', 1, true)
            ->updateValue('parent_id', 2, true);
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
    }
    
    public function testInvalidFromData1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$data argument contains unknown column name or relation name PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin->0 (\$isFromDb: false).");
        TestingAdmin::fromArray(['unknown_col']);
    }
    
    public function testInvalidFromData2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$data argument contains unknown column name or relation name PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin->unknown_col (\$isFromDb: false).");
        TestingAdmin::fromArray(['unknown_col' => 1]);
    }
    
    public function testInvalidFromData3(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage("Validation errors: [id] Value must be of an integer data type");
        TestingAdmin::fromArray(['id' => 'qqqq'], true);
    }
    
    public function testFromData(): void
    {
        $adminWithId = $this->getDataForSingleAdmin(true);
        $normalizedAdminWithId = $this->normalizeAdmin($adminWithId, false, false);
        $normalizedAdminWithId['not_existing_column_with_calculated_value'] = 'calculated-' . $normalizedAdminWithId['id'];
        $adminWithoutId = $this->getDataForSingleAdmin(false);
        $normalizedAdminWithoutId = $this->normalizeAdmin($adminWithoutId, null);
        $normalizedAdminWithoutId['password'] = null;
        $columns = array_merge(
            array_keys($adminWithId),
            [
                'password', 'not_existing_column', 'not_changeable_column',
            ]
        );
    
        $rec = TestingAdmin::fromArray([]);
        static::assertEquals($rec->getDefaults($columns, false), $rec->toArrayWithoutFiles($columns));
        
        $rec = TestingAdmin::fromArray($adminWithoutId, false);
    
        $columns[] = 'not_existing_column_with_default_value';
        $columns[] = 'not_existing_column_with_calculated_value';
        static::assertFalse($rec->isValueFromDb('parent_id'));
        static::assertEquals($normalizedAdminWithoutId, $rec->toArrayWithoutFiles($columns));
        
        $rec = TestingAdmin::_()
            ->fromData($adminWithoutId, false);
        static::assertFalse($rec->isValueFromDb('parent_id'));
        static::assertEquals($normalizedAdminWithoutId, $rec->toArrayWithoutFiles($columns));
        
        $rec = TestingAdmin::fromArray($adminWithId, true);
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertEquals($normalizedAdminWithId, $rec->toArrayWithoutFiles($columns));
        
        $rec = TestingAdmin::_()
            ->fromDbData($adminWithId);
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertEquals($normalizedAdminWithId, $rec->toArrayWithoutFiles($columns));
        
        $withUnknownColumn = array_merge($adminWithId, ['unknown_col' => 1]);
        TestingAdmin::_()
            ->fromData($withUnknownColumn, true, false);
        static::assertEquals($normalizedAdminWithId, $rec->toArrayWithoutFiles($columns));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
    }
    
    public function testFromPrimaryKey(): void
    {
        $recordsAdded = static::fillAdminsTable(10);
        $example = $recordsAdded[1];
        unset($example['password'], $example['created_at'], $example['updated_at']);
        $normalColumns = array_diff(array_keys(TestingAdmin::getColumnsThatExistInDb()), ['password', 'created_at', 'updated_at']);
        $shortSetOfColumns = ['id', 'parent_id', 'login'];
        
        $rec = TestingAdmin::newEmptyRecord()
            ->fetchByPrimaryKey($example['id']);
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals($example, $rec->toArray($normalColumns));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
        
        $rec = TestingAdmin::newEmptyRecord()
            ->fetchByPrimaryKey($example['id'], $shortSetOfColumns);
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
        $relatedRecords = $this->getObjectPropertyValue($rec, 'relatedRecords');
        static::assertCount(2, $relatedRecords);
        static::assertArrayHasKey('Parent', $relatedRecords);
        static::assertArrayHasKey('Children', $relatedRecords);
        static::assertInstanceOf(TestingAdmin::class, $relatedRecords['Parent']);
        static::assertInstanceOf(TestingAdmin::class, $rec->getRelatedRecord('Parent', false));
        static::assertInstanceOf(\PeskyORM\ORM\RecordsCollection\RecordsArray::class, $relatedRecords['Children']);
        static::assertInstanceOf(\PeskyORM\ORM\RecordsCollection\RecordsArray::class, $rec->getRelatedRecord('Children', false));
        static::assertEquals(
            $recordsAdded[0]['password'],
            $rec->getRelatedRecord('Parent', false)
                ->getValue('password')
        );
        unset($recordsAdded[0]['password']);
        $expected = $recordsAdded[0];
        $expected['created_at'] .= '+00';
        $expected['updated_at'] .= '+00';
        static::assertEquals(
            $expected,
            $rec->getRelatedRecord('Parent', false)
                ->toArrayWithoutFiles(['*' => ['not_existing_column_with_calculated_value']])
        );
        static::assertCount(2, $relatedRecords['Children']);
        static::assertCount(2, $rec->getRelatedRecord('Children', false));
        $expected = [$recordsAdded[3], $recordsAdded[7]];
        $expected[0]['created_at'] .= '+00';
        $expected[0]['updated_at'] .= '+00';
        unset($expected[0]['big_data']); //< not laoded automatically bacuase heavy
        $expected[1]['created_at'] .= '+00';
        $expected[1]['updated_at'] .= '+00';
        unset($expected[1]['big_data']); //< not laoded automatically because heavy
        static::assertEquals(
            $expected,
            $rec->getRelatedRecord('Children', false)
                ->toArrays(['*', 'password'])
        );
    }
    
    public function testInvalidColumnInFromDb1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("SELECT: TableColumn with name [invalid] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        TestingAdmin::newEmptyRecord()
            ->fetch(['id' => 1], ['invalid']);
    }
    
    public function testInvalidColumnInFromDb2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "\$columns argument contains invalid list of columns. Each value can only be a string or DbExpr object."
        );
        TestingAdmin::newEmptyRecord()
            ->fetch(['id' => 1], [['invalid']]);
    }
    
    public function testInvalidConditionInFromDb(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("WHERE: TableColumn with name [invalid] not found in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        TestingAdmin::newEmptyRecord()
            ->fetch(['invalid' => 1], ['id']);
    }
    
    public function testInvalidRelationInFromDb(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no relation 'Invalid' in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        TestingAdmin::newEmptyRecord()
            ->fetch(['id' => 1], ['id'], ['Invalid']);
    }
    
    public function testFromDb(): void
    {
        $recordsAdded = static::fillAdminsTable(10);
        [$example, $exampleWithParent] = $recordsAdded;
        unset(
            $example['password'], $example['created_at'], $example['updated_at'],
            $exampleWithParent['password'], $exampleWithParent['created_at'], $exampleWithParent['updated_at']
        );
        $normalColumns = array_diff(array_keys(TestingAdmin::getColumnsThatExistInDb()), ['password', 'created_at', 'updated_at']);
        $shortSetOfColumns = ['id', 'parent_id', 'email'];
        
        $rec = TestingAdmin::newEmptyRecord()
            ->fetch(['email' => $example['email']]);
        static::assertEquals((int)$example['id'], $rec->getValue('id'));
        static::assertEquals($example['email'], $rec->getValue('email'));
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals($example, $rec->toArray($normalColumns));
        static::assertTrue($rec->isValueFromDb('id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
        
        $rec = TestingAdmin::newEmptyRecord()
            ->fetch(['email' => $example['email']], $shortSetOfColumns);
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
        static::assertEquals(
            $example,
            $rec->getRelatedRecord('Parent', false)
                ->toArray($normalColumns)
        );
        $children = $rec->getRelatedRecord('Children', false);
        static::assertCount(2, $children->toArrays());
        static::assertEquals([$recordsAdded[3]['id'], $recordsAdded[7]['id']], Set::extract('/id', $children->toArrays()));
        
        $rec = TestingAdmin::find(['id' => $recordsAdded[0]['id']], $shortSetOfColumns, ['Parent']);
        static::assertTrue($rec->existsInDb());
        static::assertEquals(array_intersect_key($example, array_flip($shortSetOfColumns)), $rec->toArray($shortSetOfColumns));
        static::assertFalse(
            $rec->getRelatedRecord('Parent', false)
                ->existsInDb()
        );
    }
    
    public function testInvalidReload(): void
    {
        $this->expectException(RecordNotFoundException::class);
        $this->expectExceptionMessage("Record must exist in DB");
        TestingAdmin::newEmptyRecord()
            ->reload();
    }
    
    public function testReload(): void
    {
        $recordsAdded = static::fillAdminsTable(10);
        $example = $recordsAdded[0];
        $normalColumns = array_diff(array_keys(TestingAdmin::getColumnsThatExistInDb()), ['password', 'created_at', 'updated_at']);
        unset($example['password'], $example['created_at'], $example['updated_at']);
        
        $rec = TestingAdmin::newEmptyRecord()
            ->updateValue('id', $example['id'], true);
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
        static::assertFalse(
            $rec->getRelatedRecord('Parent', false)
                ->existsInDb()
        );
        static::assertCount(
            2,
            $rec->getRelatedRecord('Children', false)
                ->toArrays()
        );
        static::assertEquals(
            [$recordsAdded[1]['id'], $recordsAdded[2]['id']],
            Set::extract(
                '/id',
                $rec->getRelatedRecord('Children', false)
                    ->toArrays()
            )
        );
    }
    
    public function testInvalidReadColumns1(): void
    {
        $this->expectException(RecordNotFoundException::class);
        $this->expectExceptionMessage("Record must exist in DB");
        TestingAdmin::newEmptyRecord()
            ->readColumns();
    }
    
    public function testInvalidReadColumns2(): void
    {
        $this->expectException(RecordNotFoundException::class);
        $this->expectExceptionMessage("Record with primary key '1' was not found in DB");
        TestingAdmin::fromArray(['id' => 1], true)
            ->readColumns(['parent_id']);
    }
    
    public function testInvalidReadColumns3(): void
    {
        $this->expectException(RecordNotFoundException::class);
        $this->expectExceptionMessage("Record with primary key '1' was not found in DB");
        TestingAdmin::fromArray(['id' => 1], true)
            ->readColumns();
    }
    
    public function testReadColumns(): void
    {
        $recordsAdded = static::fillAdminsTable(1);
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
    
    public function testInvalidGetRelatedRecord1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Related record with name 'Parent' is not set and autoloading is disabled");
        TestingAdmin::newEmptyRecord()
            ->getRelatedRecord('Parent');
    }
    
    public function testInvalidGetRelatedRecord2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no relation 'InvalidRelation' in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        TestingAdmin::newEmptyRecord()
            ->getRelatedRecord('InvalidRelation');
    }
    
    public function testInvalidIsRelatedRecordAttached(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no relation 'InvalidRelation' in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        TestingAdmin::newEmptyRecord()
            ->isRelatedRecordAttached('InvalidRelation');
    }
    
    public function testInvalidSetRelatedRecord1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no relation 'InvalidRelation' in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        TestingAdmin::newEmptyRecord()
            ->updateRelatedRecord('InvalidRelation', []);
    }
    
    public function testInvalidSetRelatedRecord2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($relatedRecord) must be of type PeskyORM\ORM\Record\RecordInterface|PeskyORM\ORM\RecordsCollection\RecordsArray|PeskyORM\ORM\RecordsCollection\RecordsSet|array');
        /** @noinspection PhpParamsInspection */
        TestingAdmin::newEmptyRecord()
            ->updateRelatedRecord('Children', 'test');
    }
    
    public function testInvalidSetRelatedRecord3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$relatedRecord argument must be an instance of Record class for the 'admins' DB table");
        TestingAdmin::newEmptyRecord()
            ->updateRelatedRecord('Parent', TestingSetting::newEmptyRecord());
    }
    
    public function testInvalidSetRelatedRecord4(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($relatedRecord) must be of type PeskyORM\ORM\Record\RecordInterface|PeskyORM\ORM\RecordsCollection\RecordsArray|PeskyORM\ORM\RecordsCollection\RecordsSet|array');
        /** @noinspection PhpParamsInspection */
        TestingAdmin::newEmptyRecord()
            ->updateRelatedRecord('Parent', 'string');
    }
    
    public function testSetGetAndHasRelatedRecord(): void
    {
        $records = TestingApp::getRecordsForDb('admins', 2);
        [$parentData, $recordData] = $records;
        $normalColumns = array_diff(array_keys(TestingAdmin::getColumnsThatExistInDb()), ['password', 'created_at', 'updated_at']);
        unset(
            $parentData['password'], $parentData['created_at'], $parentData['updated_at'],
            $recordData['password'], $recordData['created_at'], $recordData['updated_at']
        );
        $normalizedParentData = $this->normalizeAdmin($parentData, true, false);
        $normalizedRecordData = $this->normalizeAdmin($recordData, true, false);
        unset($normalizedParentData['password'], $normalizedRecordData['password']);
        
        $rec = TestingAdmin::fromArray($recordData, true);
        $this->callObjectMethod($rec, 'updateRelatedRecord', 'Parent', $parentData, true);
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertEquals(
            $normalizedParentData,
            $rec->getRelatedRecord('Parent', false)
                ->toArrayWithoutFiles($normalColumns)
        );
        static::assertTrue(
            $rec->getRelatedRecord('Parent', false)
                ->existsInDb()
        );
        
        $rec = TestingAdmin::fromArray(array_merge($recordData, ['Parent' => $parentData]), true);
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertEquals(
            $normalizedParentData,
            $rec->getRelatedRecord('Parent', false)
                ->toArrayWithoutFiles($normalColumns)
        );
        static::assertTrue(
            $rec->getRelatedRecord('Parent', false)
                ->existsInDb()
        );
        static::assertEquals(
            array_merge($normalizedRecordData, ['Parent' => $normalizedParentData]),
            $rec->toArrayWithoutFiles($normalColumns, ['Parent' => $normalColumns], false)
        );
    }
    
    public function testRelationsUnsettingOnForeignKeyChange(): void
    {
        $records = TestingApp::getRecordsForDb('admins', 2);
        [$parentData, $recordData] = $records;
        $recordData['Parent'] = $parentData;
    
        $rec = TestingAdmin::fromArray($recordData, true);
        static::assertTrue($rec->Parent->existsInDb());
        static::assertEquals($rec->parent_id, $rec->Parent->id);
        
        $rec->setParentId(null);
        static::assertNull($rec->parent_id);
        // while parent_id value is not saved to db - related records will not be removed
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertEquals($recordData['parent_id'], $rec->Parent->id);
        
        $rec->updateValue('parent_id', null, true);
        // now parent_id value is from DB so unrelated relations should be removed
        static::assertFalse($rec->isRelatedRecordAttached('Parent'));
        static::assertFalse($rec->Parent->existsInDb());
    }
    
    public function testInvalidReadRelatedRecord1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no relation 'InvalidRelation' in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        TestingAdmin::newEmptyRecord()
            ->readRelatedRecord('InvalidRelation');
    }
    
    public function testInvalidReadRelatedRecord2(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage(
            "Record PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin has not enough data to read related record 'Parent'. You need to provide a value for 'parent_id' column."
        );
        TestingAdmin::newEmptyRecord()
            ->readRelatedRecord('Parent');
    }
    
    public function testReadRelatedRecord(): void
    {
        $recordsAdded = static::fillAdminsTable(10);
        $parentData = $recordsAdded[0];
        $normalColumns = array_diff(array_keys(TestingAdmin::getColumnsThatExistInDb()), ['created_at', 'updated_at']);
        unset($parentData['created_at'], $parentData['updated_at'], $parentData['big_data']);
        
        $rec = TestingAdmin::fromArray($recordsAdded[1], true);
        static::assertFalse($rec->isRelatedRecordAttached('Parent'));
        static::assertFalse($rec->isRelatedRecordAttached('Children'));
        static::assertEquals($parentData['id'], $rec->getValue('parent_id'));
        $prevSqlQuery = TestingAdminsTable::getLastQuery(false);
        static::assertTrue(
            $rec->getRelatedRecord('Parent', true)
                ->existsInDb()
        );
        static::assertNotEquals($prevSqlQuery, TestingAdminsTable::getLastQuery(false));
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertEquals(
            $parentData,
            $rec->getRelatedRecord('Parent', false)
                ->toArray($normalColumns)
        );
        $prevSqlQuery = TestingAdminsTable::getLastQuery(false);
        static::assertInstanceOf(RecordsSet::class, $rec->getRelatedRecord('Children', true));
        static::assertEquals($prevSqlQuery, TestingAdminsTable::getLastQuery(false)); //< RecordsSet is lazy - query is still the same
        static::assertCount(2, $rec->getRelatedRecord('Children', true));
        static::assertNotEquals($prevSqlQuery, TestingAdminsTable::getLastQuery(false)); //< query was launched by getRelatedRecord()
        static::assertStringNotContainsString('COUNT(', TestingAdminsTable::getLastQuery(false));
        TestingAdminsTable::selectValue(DbExpr::create('1'));
        $prevSqlQuery = TestingAdminsTable::getLastQuery(false);
        static::assertStringContainsString('SELECT (1) FROM', $prevSqlQuery);
        static::assertEquals(
            [$recordsAdded[3]['id'], $recordsAdded[7]['id']],
            Set::extract(
                '/id',
                $rec->getRelatedRecord('Children', false)
                    ->toArrays()
            )
        );
        static::assertEquals($prevSqlQuery, TestingAdminsTable::getLastQuery(false)); //< repeated calls to getRelatedRecord() should not do any DB queries
        
        // change id and test if relations were erased
        $rec->updateValue('id', $parentData['id'], true);
        static::assertFalse($rec->isRelatedRecordAttached('Parent'));
        static::assertFalse($rec->isRelatedRecordAttached('Children'));
    }
    
    public function testInvalidUpdateValuesData1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$data argument contains unknown column name or relation name PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin->invalid_col (\$isFromDb: true). Possibly column 'invalid_col' exists in DB but not defined in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        TestingAdmin::newEmptyRecord()
            ->updateValues(['id' => 1, 'invalid_col' => 2], true);
    }
    
    public function testInvalidUpdateValuesData2(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Argument #2 ($relatedRecord) must be of type PeskyORM\ORM\Record\RecordInterface|PeskyORM\ORM\RecordsCollection\RecordsArray|PeskyORM\ORM\RecordsCollection\RecordsSet|array');
        TestingAdmin::newEmptyRecord()
            ->updateValues(['id' => 1, 'Parent' => null, 'Parent2' => null], true);
    }
    
    public function testInvalidUpdateValuesData3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$data argument contains unknown column name or relation name PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin->Parent2 (\$isFromDb: true). Possibly column 'Parent2' exists in DB but not defined in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure");
        TestingAdmin::newEmptyRecord()
            ->updateValues(['id' => 1, 'Parent' => [], 'Parent2' => null], true);
    }
    
    public function testInvalidUpdateValuesData4(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage("Validation errors: [email] Value must be an email");
        TestingAdmin::newEmptyRecord()
            ->updateValues(['email' => 'not email']);
    }
    
    public function testInvalidUpdateValuesData5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Values update failed: record PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin does not exist in DB while \$isFromDb === true. Possibly you've missed a primary key value in \$data argument.");
        TestingAdmin::newEmptyRecord()
            ->updateValues(['email' => 'test@email.cc'], true);
    }
    
    public function testInvalidUpdateValuesData6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("It is forbidden to change primary key value when \$isFromDb === false");
        TestingAdmin::newEmptyRecord()
            ->updateValues(['id' => 1], false);
    }
    
    public function testUpdateValues(): void
    {
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
        static::assertFalse(
            $rec->getRelatedRecord('Parent', false)
                ->existsInDb(false)
        );
        
        $rec->updateValues(['Parent' => $records[0]], true);
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertTrue(
            $rec->getRelatedRecord('Parent', false)
                ->existsInDb(false)
        );
        static::assertEquals(
            $records[0]['password'],
            $rec->getRelatedRecord('Parent', false)
                ->getValue('password')
        );
        unset($records[0]['password']);
        static::assertEquals(
            $records[0],
            $rec->getRelatedRecord('Parent', false)
                ->toArrayWithoutFiles(['*' => ['not_existing_column_with_calculated_value']])
        );
        
        $rec->merge(['Parent' => [], 'email' => null], false);
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertFalse(
            $rec->getRelatedRecord('Parent', false)
                ->existsInDb(false)
        );
        static::assertNull($rec->getValue('email'));
        static::assertFalse($rec->isValueFromDb('email'));
        
        $rec->merge(['invalid_column' => 1, 'email' => 'qqqq@qqqq.qq'], false, false);
    }
    
    public function testGetColumnsNamesWithUpdatableValues(): void
    {
        $rec = TestingAdmin::newEmptyRecord();
        $updateable = $rec->getColumnsNamesWithUpdatableValues();
        $expected = [
            'id', 'parent_id', 'login', 'password', 'created_at', 'updated_at',
            'remember_token', 'is_superadmin', 'language', 'ip', 'role', 'is_active',
            'name', 'email', 'timezone', 'big_data',
        ];
        static::assertEquals($expected, $updateable);
    }
    
    public function testGetAllColumnsWithAutoUpdatingValues(): void
    {
        $rec = TestingAdmin::newEmptyRecord();
        $updateable = $this->callObjectMethod($rec, 'getAllColumnsWithAutoUpdatingValues');
        static::assertEquals(['updated_at'], $updateable);
    }
    
    public function testInvalidSave(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Attempt to save data after begin(). You must call commit() or rollback()");
        TestingAdmin::fromArray(['id' => 1], true)
            ->begin()
            ->save();
    }
    
    public function testInvalidSaveToDb1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$columnsToSave argument contains invalid list of columns. Each value can only be a string. $columns =');
        $this->callObjectMethod(
            TestingAdmin::fromArray(['id' => 1], true),
            'saveToDb',
            ['id', 'qwejklqe', 'asdqwe', ['qqq']]
        );
    }
    
    public function testInvalidSaveToDb2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            '$columnsToSave argument contains columns that cannot be saved to DB: some_file, not_changeable_column, not_existing_column'
        );
        $this->callObjectMethod(
            TestingAdmin::fromArray(['id' => 1], true),
            'saveToDb',
            ['id', 'some_file', 'not_changeable_column', 'not_existing_column']
        );
    }
    
    public function testInvalidDataInCollectValuesForSave(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage("Validation errors: [email] Value must be an email");
        $columns = ['parent_id', 'email', 'login'];
        TestingAdmin::fromArray(['id' => 1, 'parent_id' => null, 'email' => 'invalid', 'login' => 'asd'], true)
            ->collectValuesForSave($columns, false);
    }
    
    public function testCollectValuesForSaveAndValidateNewData(): void
    {
        static::assertEquals(['Value must be an email.'], TestingAdmin::validateValue('email', 'invalid'));
        static::assertEquals([], TestingAdmin::validateValue('email', 'valid@email.cc'));
        
        $originalData = TestingApp::getRecordsForDb('admins', 1)[0];
        $rec = TestingAdmin::fromArray($originalData, true);
        $columnsToSave = $rec->getColumnsNamesWithUpdatableValues();
        static::assertEquals(
            [],
            $rec->collectValuesForSave($columnsToSave, true)
        );
        $updates = array_diff_key($originalData, array_flip(['id', 'not_changeable_column', 'password']));
        $rec->updateValues($updates, false);
        static::assertEquals(
            [], //< data actually was not modified for any column
            $rec->collectValuesForSave($columnsToSave, true)
        );
    
        $rec->reset()
            ->updateValue('id', $originalData['id'], true);
        $rec->updateValues($updates);
        $expectedData = array_merge($updates, ['id' => $originalData['id']]);
        static::assertEquals(
            $expectedData,
            $rec->collectValuesForSave($columnsToSave, true)
        );

        $rec->reset()
            ->updateValue('id', $originalData['id'], true);
        $rec->updateValues(array_diff_key($updates, ['updated_at' => '']));
        $expectedData = array_merge($updates, ['id' => $originalData['id'], 'updated_at' => DbExpr::create('NOW()')]);
        static::assertEquals(
            $expectedData,
            $rec->collectValuesForSave($columnsToSave, true)
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
            $rec->collectValuesForSave($columnsToSave, false)
        );

        $rec->reset();
        $defaults = array_filter(
            $rec->getDefaults($columnsToSave, true, false),
            static function ($value) {
                return $value !== null;
            }
        );
        $expectedData = array_merge(
            [
                'id' => TestingAdminsTable::getExpressionToSetDefaultValueForAColumn(),
                'updated_at' => DbExpr::create('NOW()'),
            ],
            $defaults
        );
        static::assertEquals(
            $expectedData,
            $rec->collectValuesForSave($columnsToSave, false)
        );

        $rec->reset();
        $expectedData = ['id' => 1, 'parent_id' => null, 'email' => 'test@test.cc', 'login' => 'test'];
        $rec->fromData($expectedData, true);
        static::assertEquals(
            [],
            $rec->collectValuesForSave($columnsToSave, false)
        );
    }
    
    /**
     * @covers Record::beforeSave()
     *
     *
     */
    public function testBeforeSave(): void
    {
        $this->expectException(InvalidDataException::class);
        $this->expectExceptionMessage("Validation errors: [login] error");
        $rec = TestingAdmin2::newEmptyRecord();
        $rec
            ->fromData(['id' => 999, 'login' => 'qqq'], true)
            ->updateValue('password', 'test', false)
            ->save();
    }
    
    /**
     * @covers Record::afterSave()
     */
    public function testAfterSave(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("after: no-no-no!");
        $rec = TestingAdmin2::newEmptyRecord();
        $rec
            ->updateValue('login', 'test', false)
            ->updateValue('password', 'test', false)
            ->save();
    }
    
    public function testInvalidColumnSavingExtendersCall1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Trying to begin collecting changes on not existing record");
        $rec = TestingAdmin3::newEmptyRecord();
        $rec->runColumnSavingExtenders(['id'], ['id' => 1], ['id' => 1], true);
    }
    
    public function testColumnSavingExtenders1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("login: update!");
        $data = ['id' => 1, 'parent_id' => null, 'login' => 'test'];
        $rec = TestingAdmin3::fromArray($data, true);
        $rec->runColumnSavingExtenders(['id', 'parent_id', 'login'], $data, $data, true);
    }
    
    public function testColumnSavingExtenders2(): void
    {
        $dataSaved = ['parent_id' => null, 'login' => 'test'];
        $dataReceivedAfterUpdate = ['id' => 1, 'parent_id' => null, 'login' => 'test'];
        $rec = TestingAdmin3::fromArray($dataReceivedAfterUpdate, true);
        $rec->runColumnSavingExtenders(['parent_id', 'login'], $dataSaved, $dataSaved, false);
        static::assertTrue(true); //< no exception expected here
    }
    
    public function testColumnSavingExtenders3(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("some_file: here");
        $rec = TestingAdmin3::fromArray(['id' => 1, 'parent_id' => null, 'login' => 'test'], true);
        $rec->updateValue('some_file', [
            'tmp_name' => __DIR__ . '/files/test_file.jpg',
            'name' => 'image.jpg',
            'type' => 'image/jpeg',
            'size' => filesize(__DIR__ . '/files/test_file.jpg'),
            'error' => 0,
        ], false);
        $rec->runColumnSavingExtenders(['parent_id', 'some_file'], ['parent_id' => null, 'some_file' => 'qqq'], ['id' => 1, 'parent_id' => null], true);
    }
    
    public function testColumnSavingExtenders4(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("some_file: here");
        $rec = TestingAdmin3::fromArray(['id' => 1, 'parent_id' => null, 'login' => 'test'], true);
        $rec->updateValue('some_file', [
            'tmp_name' => __DIR__ . '/files/test_file.jpg',
            'name' => 'image.jpg',
            'type' => 'image/jpeg',
            'size' => filesize(__DIR__ . '/files/test_file.jpg'),
            'error' => 0,
        ], true);
        $rec->runColumnSavingExtenders(['parent_id', 'some_file'], ['parent_id' => null, 'some_file' => 'qqq'], ['id' => 1, 'parent_id' => null], true);
    }
    
    public function testColumnSavingExtenders5(): void
    {
        // this one should not call any extenders that throw exceptions
        $rec = TestingAdmin3::fromArray(['id' => 1, 'parent_id' => null, 'login' => 'test'], true);
        $rec->runColumnSavingExtenders(['parent_id', 'some_file'], ['parent_id' => null], ['id' => 1, 'parent_id' => null], true);
        static::assertTrue(true);
    }
    
    /**
     * @covers Record::runColumnSavingExtenders()
     */
    public function testColumnSavingExtendersUsageInSave1(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("login: update!");
        static::fillAdminsTable(1);
        $rec = TestingAdmin3::newEmptyRecord()
            ->fromData(['id' => 1], true)
            ->updateValues(['parent_id' => null, 'login' => 'test']);
        $rec->save();
    }
    
    /**
     * @covers Record::runColumnSavingExtenders()
     */
    public function testColumnSavingExtendersUsageInSave2(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("some_file: here");
        static::fillAdminsTable(1);
        $rec = TestingAdmin3::newEmptyRecord()
            ->fromData(['id' => 1], true)
            ->updateValues([
                'parent_id' => null,
                'some_file' => [
                    'tmp_name' => __DIR__ . '/files/test_file.jpg',
                    'name' => 'image.jpg',
                    'type' => 'image/jpeg',
                    'size' => filesize(__DIR__ . '/files/test_file.jpg'),
                    'error' => 0,
                ],
            ]);
        $rec->save();
    }
    
    /**
     * @covers Record::save()
     * @covers Record::saveToDb()
     */
    public function testSaveAndSaveToDbAndBeforeAfterSave(): void
    {
        $recordsAdded = static::fillAdminsTable(10);
        static::assertEquals(10, TestingAdminsTable::count([]));
        $rec = TestingAdmin::newEmptyRecord();
        // insert
        $newRec = array_diff_key($recordsAdded[0], array_flip(['id', 'not_changeable_column', 'password']));
        $newRec['email'] = $newRec['login'] = 'testemail1@mail.com';
        $expected0 = $newRec;
        $expected0['created_at'] .= '+00';
        $rec->fromData($newRec)
            ->updateValue('password', 'test', false)
            ->save();
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertNotEquals($newRec['updated_at'], $rec->getValue('updated_at'));
        static::assertTrue(password_verify('test', $rec->getValue('password')));
        unset($newRec['updated_at'], $expected0['updated_at']);
        static::assertEquals($expected0, $rec->toArrayWithoutFiles(array_keys($newRec)));
        static::assertEquals(11, TestingAdminsTable::count([]));
        // update
        $newRec['email'] = $newRec['login'] = $expected0['email'] = $expected0['login'] = 'testemail2@mail.com';
        $rec->fromData($recordsAdded[1], true)
            ->updateValues($newRec)
            ->save();
        static::assertTrue($rec->existsInDb());
        static::assertTrue($rec->existsInDb(true));
        static::assertEquals($recordsAdded[1]['password'], $rec->getValue('password'));
        static::assertEquals($expected0, $rec->toArrayWithoutFiles(array_keys($newRec)));
        static::assertEquals(11, TestingAdminsTable::count([]));
        // update not exising id
        $rec->updateValue('id', 0, true)
            ->save();
        static::assertFalse($rec->existsInDb());
        static::assertFalse($rec->existsInDb(true));
        static::assertEquals(11, TestingAdminsTable::count([]));
    }
    
    /**
     * @covers Record::save()
     * @covers Record::saveToDb()
     */
    public function testSaveAndSaveToDbAndBeforeAfterSaveWithRelations(): void
    {
        $recordsAdded = static::fillAdminsTable(10);
        static::assertEquals(10, TestingAdminsTable::count([]));
        
        $newRec['email'] = $newRec['login'] = 'testemail3@mail.com';
        $rec = TestingAdmin::fromArray($newRec, false)
            ->updateValue('password', 'test1', false);
        $child1 = array_merge(
            $recordsAdded[1],
            ['parent_id' => null, 'id' => null, 'password' => 'test', 'email' => 'testemail4@mail.com', 'login' => 'testemail4@mail.com']
        );
        $child2 = array_merge(
            $recordsAdded[2],
            ['parent_id' => null, 'id' => null, 'password' => 'test2', 'email' => 'testemail5@mail.com', 'login' => 'testemail5@mail.com']
        );
        unset($child1['not_changeable_column'], $child2['not_changeable_column']);
        $rec->updateValues(['Children' => [$child1, $child2]], false);
        static::assertEquals(10, TestingAdminsTable::count());
        
        $rec->save(['Children']);
        static::assertEquals(13, TestingAdminsTable::count());
        static::assertNull($rec->getValue('parent_id')); //< should not be changed
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertCount(2, $rec->getRelatedRecord('Children'));
        
        $rec->reload([], ['Children']);
        static::assertNull($rec->getValue('parent_id'));
        $expected1 = array_diff_key($child1, array_flip(['id', 'updated_at', 'password', 'big_data']));
        $expected1['created_at'] .= '+00';
        $expected2 = array_diff_key($child2, array_flip(['id', 'updated_at', 'password', 'big_data']));
        $expected2['created_at'] .= '+00';
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
    
    public function testInvalidBegin1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Trying to begin collecting changes on not existing record");
        TestingAdmin::newEmptyRecord()
            ->begin();
    }
    
    public function testInvalidBegin2(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Trying to begin collecting changes on not existing record");
        TestingAdmin::fromArray(['parent_id' => 1], false)
            ->begin();
    }
    
    public function testInvalidBegin3(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Attempt to begin collecting changes when already collecting changes");
        TestingAdmin::fromArray(['id' => 1], true)
            ->begin()
            ->begin();
    }
    
    public function testInvalidBegin4(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage(
            "Attempt to reset record while changes collecting was not finished. You need to use commit() or rollback() first"
        );
        TestingAdmin::fromArray(['id' => 1], true)
            ->begin()
            ->reset();
    }
    
    public function testInvalidRollback1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("It is impossible to rollback changed values: changes collecting was not started");
        TestingAdmin::newEmptyRecord()
            ->rollback();
    }
    
    public function testBeginAndRollback(): void
    {
        $rec = TestingAdmin::fromArray(['id' => 1], true);
        $rec->begin();
        static::assertTrue($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertEquals([], $this->getObjectPropertyValue($rec, 'valuesBackup'));
        $rec->rollback();
        static::assertFalse($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertEquals([], $this->getObjectPropertyValue($rec, 'valuesBackup'));
        $rec->begin();
        $rec->updateValue('email', 'email.was@changed.hehe', false);
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('email', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertInstanceOf(RecordValue::class, $this->getObjectPropertyValue($rec, 'valuesBackup')['email']);
        static::assertFalse($this->getObjectPropertyValue($rec, 'valuesBackup')['email']->hasValue());
        static::assertEquals('email.was@changed.hehe', $rec->getValue('email'));
        $rec->rollback();
        static::assertFalse($this->getObjectPropertyValue($rec, 'isCollectingUpdates'));
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertFalse($rec->hasValue('email'));

        $rec->begin();
        $rec->updateValue('email', 'email.was@changed.hehe', false);
        $rec->updateValue('login', 'email.was@changed.hehe', false);
        static::assertCount(2, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('email', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('login', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertInstanceOf(RecordValue::class, $this->getObjectPropertyValue($rec, 'valuesBackup')['email']);
        static::assertInstanceOf(RecordValue::class, $this->getObjectPropertyValue($rec, 'valuesBackup')['login']);
        static::assertFalse($this->getObjectPropertyValue($rec, 'valuesBackup')['email']->hasValue());
        static::assertFalse($this->getObjectPropertyValue($rec, 'valuesBackup')['login']->hasValue());
        static::assertEquals('email.was@changed.hehe', $rec->getValue('email'));
        static::assertEquals('email.was@changed.hehe', $rec->getValue('login'));
        $rec->rollback();
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertFalse($rec->hasValue('email'));
        static::assertFalse($rec->hasValue('login'));

        $data = TestingApp::getRecordsForDb('admins', 1)[0];
        $data = array_merge($data, ['not_existing_column' => null]);
        $rec->fromData($data, true); //< it should not fail even if there was no commit() or rollback() after previous begin()
        static::assertEquals($data['password'], $rec->getValue('password'));
        unset($data['password']);
        static::assertEquals($data, $rec->toArrayWithoutFiles(array_keys($data)));
        $rec->begin()
            ->rollback()
            ->begin();
        $rec->updateValue('email', 'email.was@changed.hehe', false);
        static::assertCount(1, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('email', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertEquals($data['email'], $this->getObjectPropertyValue($rec, 'valuesBackup')['email']->getValue());
        static::assertEquals('email.was@changed.hehe', $rec->getValue('email'));
        $rec->rollback();
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertTrue($rec->hasValue('email'));
        static::assertEquals($data['email'], $rec->getValue('email'));
        static::assertEquals($data, $rec->toArrayWithoutFiles(array_keys($data)));
        
        $rec->begin();
        static::assertEquals($data, $rec->toArrayWithoutFiles(array_keys($data)));
        $rec->updateValue('email', 'email.was@changed.hehe', false);
        $rec->updateValue('login', 'email.was@changed.hehe', false);
        static::assertCount(2, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('email', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertArrayHasKey('login', $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertEquals($data['email'], $this->getObjectPropertyValue($rec, 'valuesBackup')['email']->getValue());
        static::assertEquals($data['login'], $this->getObjectPropertyValue($rec, 'valuesBackup')['login']->getValue());
        static::assertEquals('email.was@changed.hehe', $rec->getValue('email'));
        static::assertEquals('email.was@changed.hehe', $rec->getValue('login'));
        $rec->rollback();
        static::assertCount(0, $this->getObjectPropertyValue($rec, 'valuesBackup'));
        static::assertTrue($rec->hasValue('email'));
        static::assertTrue($rec->hasValue('login'));
        static::assertEquals($data, $rec->toArrayWithoutFiles(array_keys($data)));
    }
    
    public function testInvalidCommit1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("It is impossible to commit changed values: changes collecting was not started");
        TestingAdmin::newEmptyRecord()
            ->commit();
    }
    
    /**
     * @covers Record::commit()
     */
    public function testCommit(): void
    {
        $recordsAdded = static::fillAdminsTable(10);
        static::assertEquals(10, TestingAdminsTable::count([]));
        $rec = TestingAdmin::fromArray($recordsAdded[2], true);
        $expected = array_diff_key($recordsAdded[2], array_flip(['updated_at', 'password']));
        static::assertEquals($expected, $rec->toArrayWithoutFiles(array_keys($expected)));
        $update = array_diff_key($recordsAdded[0], array_flip(['id', 'password', 'not_changeable_column']));
        $update['email'] = 'testemail1@mail.com';
        $update['login'] = 'testemail1@mail.com';
        $rec
            ->begin()
            ->updateValues($update, false)
            ->commit()
            ->reload();
        unset($update['updated_at']);
        $expected = array_merge($expected, $update);
        $expected['created_at'] .= '+00';
        static::assertEquals($expected, $rec->toArrayWithoutFiles(array_keys($expected)));
        static::assertNotEquals($recordsAdded[2]['updated_at'], $rec->getValue('updated_at'));
        static::assertNotEquals($recordsAdded[0]['updated_at'], $rec->getValue('updated_at'));
        static::assertEquals($recordsAdded[2]['password'], $rec->getValue('password'));
        static::assertEquals($recordsAdded[2]['not_changeable_column'], $rec->getValue('not_changeable_column'));
        // test password change
        $rec
            ->begin()
            ->updateValue('password', 'test1111', false)
            ->commit();
        $expected = array_merge($expected, $update);
        $expected['created_at'] .= '+00';
        static::assertEquals($expected, $rec->toArrayWithoutFiles(array_keys($expected)));
        static::assertNotEquals($recordsAdded[0]['password'], $rec->getValue('password'));
        static::assertNotEquals($recordsAdded[2]['password'], $rec->getValue('password'));
        static::assertTrue(password_verify('test1111', $rec->getValue('password')));
        static::assertTrue(
            password_verify(
                'test1111',
                $rec->reload()
                    ->getValue('password')
            )
        );
    }
    
    public function testInvalidSaveRelations1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("It is impossible to save related objects of a record that does not exist in DB");
        TestingAdmin::newEmptyRecord()
            ->saveRelations(['Parent']);
    }
    
    public function testInvalidSaveRelations2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('$relationsToSave argument contains invalid list of columns. Each value can only be a string. $relationsToSave = ["NotRelation",["asd"]]');
        TestingAdmin::newEmptyRecord()
            ->updateValue('id', 1, true)
            ->saveRelations(['NotRelation', ['asd']]);
    }
    
    public function testSaveRelations(): void
    {
        $recordsAdded = static::fillAdminsTable(10);
        $parent = array_merge($recordsAdded[2], ['parent_id' => null, 'id' => null, 'password' => 'test']);
        $parent['email'] = $parent['login'] = 'testemail2@mail.com';
        unset($parent['not_changeable_column']);
        // belongs to while record exists
        $rec = TestingAdmin::fromArray($recordsAdded[0], true);
        $rec['email'] = $rec['login'] = 'testemail1@mail.com';
        $rec->updateValues(['Parent' => $parent], false);
        static::assertEquals(10, TestingAdminsTable::count());
        $rec->saveRelations(['Parent']);
        static::assertEquals(11, TestingAdminsTable::count());
        static::assertNotNull($rec->getValue('parent_id'));
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertTrue(
            $rec->getRelatedRecord('Parent')
                ->existsInDb(true)
        );
        $rec->reload([], ['Parent']);
        static::assertNotNull($rec->getValue('parent_id'));
        $expected = array_diff_key($parent, array_flip(['id', 'updated_at', 'password']));
        $expected['created_at'] .= '+00';
        static::assertEquals(
            $expected,
            $rec->getRelatedRecord('Parent')
                ->toArrayWithoutFiles(array_keys($expected))
        );
        static::assertEquals(
            $rec->getValue('parent_id'),
            $rec->getRelatedRecord('Parent')
                ->getPrimaryKeyValue()
        );
        static::assertTrue(
            password_verify(
                'test',
                $rec->getRelatedRecord('Parent')
                    ->getValue('password')
            )
        );
        
        // belongs to while record not exists - it is forbidden but maybe later
        /*$rec->unsetPrimaryKeyValue();
        $rec->updateValues(['Parent' => $parent], false);
        $rec->saveRelations(['Parent']);
        static::assertEquals(12, TestingAdminsTable::count());
        static::assertNotNull($rec->getValue('parent_id'));
        static::assertFalse($rec->isValueFromDb('parent_id'));*/
        
        // has one
        static::fillAdminsTable(1);
        $rec = TestingAdmin::fromArray($recordsAdded[0], true);
        $rec->updateValues(['HasOne' => $parent], false);
        static::assertEquals(1, TestingAdminsTable::count());
        $rec->saveRelations(['HasOne']);
        static::assertEquals(2, TestingAdminsTable::count());
        static::assertNull($rec->getValue('parent_id')); //< should not be changed
        static::assertTrue($rec->isValueFromDb('parent_id'));
        static::assertTrue(
            $rec->getRelatedRecord('HasOne')
                ->existsInDb(true)
        );
        $rec->reload([], ['HasOne']);
        static::assertNull($rec->getValue('parent_id'));
        $expected = array_diff_key($parent, array_flip(['id', 'updated_at', 'password']));
        $expected['parent_id'] = $rec->getPrimaryKeyValue();
        $expected['created_at'] .= '+00';
        static::assertEquals(
            $expected,
            $rec->getRelatedRecord('HasOne')
                ->toArrayWithoutFiles(array_keys($expected))
        );
        static::assertEquals(
            $rec->getPrimaryKeyValue(),
            $rec->getRelatedRecord('HasOne')
                ->getValue('parent_id')
        );
        static::assertTrue(
            password_verify(
                'test',
                $rec->getRelatedRecord('HasOne')
                    ->getValue('password')
            )
        );
        
        // has many
        static::fillAdminsTable(1);
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
        $expected1 = array_diff_key($child1, array_flip(['id', 'updated_at', 'password', 'big_data']));
        $expected1['created_at'] .= '+00';
        $expected2 = array_diff_key($parent, array_flip(['id', 'updated_at', 'password', 'big_data']));
        $expected2['created_at'] .= '+00';
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
    
    public function testInvalidDelete(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("It is impossible to delete record has no primary key value");
        TestingAdmin::newEmptyRecord()
            ->delete();
    }
    
    /**
     * @covers \PeskyORM\ORM\Record\Record::beforeDelete()
     *
     *
     */
    public function testBeforeDelete(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("before delete: no-no-no!");
        TestingAdmin2::fromArray(['id' => 9999], true)
            ->delete();
    }
    
    /**
     * @covers Record::afterDelete()
     */
    public function testAfterDelete(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("after delete: no-no-no!");
        TestingAdmin2::fromArray(['id' => 0], true)->delete();
    }
    
    /**
     * @covers Record::delete()
     */
    public function testDelete(): void
    {
        $addedRecords = static::fillAdminsTable(10);
        static::assertEquals(10, TestingAdminsTable::count());
        $rec = TestingAdmin::fromArray($addedRecords[1], true);
        $rec->delete();
        static::assertEquals(9, TestingAdminsTable::count());
        static::assertFalse(
            TestingAdmin::read($addedRecords[1]['id'])
                ->existsInDb()
        );
    }
    
    /**
     * @covers \PeskyORM\ORM\Record\Record::current()
     * @covers Record::valid()
     * @covers Record::key()
     * @covers Record::next()
     * @covers Record::rewind()
     */
    public function testIterations(): void
    {
        $rec = TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true);
        $cols = [];
        foreach ($rec as $name => $value) {
            // it works like iteration over $rec->toArray()
            static::assertEquals($this->callObjectMethod($rec, 'getColumnValueForToArray', $name), $value);
            $cols[] = $name;
        }
        sort($cols);
        $expected = array_diff(array_keys($rec::getColumns()), ['password']);
        sort($expected);
        static::assertEquals($expected, $cols);
        $count = 0;
        foreach ($rec as $ignored) {
            // to test rewind
            $count++;
        }
        static::assertCount($count, $expected);
    }
    
    public function testInvalidArrayAccess1(): void
    {
        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('PeskyORM\ORM\Record\Record::hasColumn(): Argument #1 ($name) must be of type string');
        $rec = TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true);
        $rec[0];
    }
    
    public function testInvalidArrayAccess2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [invalidcolname] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        $rec = TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true);
        $rec['invalidcolname'];
    }
    
    public function testInvalidMagicGetter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [invalidcolname] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        /** @noinspection PhpUndefinedFieldInspection */
        TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true)->invalidcolname;
    }
    
    public function testInvalidMagicIsset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [invalidcolname] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        isset(TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true)->invalidcolname);
    }
    
    public function testInvalidArrayOffsetIsset1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [invalidcolname] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        isset(TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true)['invalidcolname']);
    }
    
    public function testArrayOffsetIssetWithFormatter(): void
    {
        static::assertTrue(isset(TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true)['created_at_as_date']));
    }
    
    public function testInvalidArrayOffsetUnset1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [invalidcolname] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        unset(TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true)['invalidcolname']);
    }
    
    public function testInvalidArrayOffsetUnset2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [created_at_as_date] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        unset(TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true)['created_at_as_date']);
    }
    
    public function testInvalidMagicPropertyUnset1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [invalidcolname] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        unset(TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true)->invalidcolname);
    }
    
    public function testInvalidMagicPropertyUnset2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [created_at_as_date] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        unset(TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true)->created_at_as_date);
    }
    
    public function testGetValueWothFormatterInName(): void
    {
        $record = TestingAdmin::fromArray(TestingApp::getRecordsForDb('admins', 1)[0], true);
        $expected = date('Y-m-d', strtotime($record->created_at));
        
        static::assertEquals($expected, $record->getValue('created_at', 'date'));
        static::assertEquals($expected, $record->getValue('created_at_as_date'));
        static::assertEquals($expected, $record->getValue('created_at_as_time', 'date'));
    }
    
    /**
     * @covers Record::offsetExists()
     * @covers Record::offsetGet()
     * @covers Record::__get()
     * @covers Record::__isset()
     * @noinspection PhpUnitAssertCanBeReplacedWithEmptyInspection
     */
    public function testMagicGetterAndOffsetGetAndIssetAndUnset(): void
    {
        /** @var TestingAdmin $rec */
        $data = TestingApp::getRecordsForDb('admins', 1)[0];
        $rec = TestingAdmin::fromArray($data, true);
        $rec->updateValues(['Parent' => $data, 'Children' => [$data, $data]], true);
        foreach ($rec::getColumns() as $name => $config) {
            if ($config->isReal()) {
                static::assertEquals(isset($data[$name]), isset($rec->$name), "isset property: $name");
                static::assertEquals(isset($data[$name]), isset($rec[$name]), "isset array key: $name");
                static::assertEquals($rec->getValue($name), $rec->$name, "get property: $name");
                static::assertEquals($rec->getValue($name), $rec[$name], "get array key: $name");
            }
        }
        // special
        static::assertEquals('2015-05-14', $rec->created_at_as_date);
        static::assertEquals('2015-05-14', $rec['created_at_as_date']);
        // relation
        static::assertTrue(isset($rec->Parent));
        static::assertNotEmpty($rec->Parent);
        static::assertTrue(isset($rec->Children));
        static::assertNotEmpty($rec->Children);
        static::assertTrue(isset($rec['Parent']));
        static::assertNotEmpty($rec['Parent']);
        static::assertTrue(isset($rec['Children']));
        static::assertNotEmpty($rec['Children']);
        static::assertInstanceOf(TestingAdmin::class, $rec->Parent);
        static::assertInstanceOf(\PeskyORM\ORM\RecordsCollection\RecordsArray::class, $rec->Children);
        static::assertInstanceOf(TestingAdmin::class, $rec['Parent']);
        static::assertInstanceOf(\PeskyORM\ORM\RecordsCollection\RecordsArray::class, $rec['Children']);
        static::assertEquals($data['password'], $rec->Parent->getValue('password'));
        unset($data['password']);
        static::assertEquals($data, $rec->Parent->toArray(array_keys($data)));
        static::assertCount(2, $rec->Children);
        static::assertEquals($data, $rec['Parent']->toArray(array_keys($data)));
        static::assertCount(2, $rec['Children']);
        // unset columns
        unset($rec->parent_id);
        static::assertFalse($rec->hasValue('parent_id'));
        static::assertFalse(isset($rec->parent_id));
        /** @noinspection PhpUnitTestsInspection */
        static::assertTrue(empty($rec->parent_id));
        unset($rec['language']);
        static::assertFalse($rec->hasValue('parent_id'));
        static::assertFalse(isset($rec['language']));
        /** @noinspection PhpUnitTestsInspection */
        static::assertTrue(empty($rec['language']));
        // relations
        unset($rec->Parent);
        static::assertFalse($rec->isRelatedRecordAttached('Parent'));
        static::assertFalse(isset($rec->Parent));
        /** @noinspection PhpUnitTestsInspection */
        static::assertTrue(empty($rec->Parent));
        unset($rec['Children']);
        static::assertFalse($rec->isRelatedRecordAttached('Children'));
        static::assertFalse(isset($rec['Children']));
        static::assertEmpty($rec['Children']);
        // specific situations for relations and isset/empty
        $recordsInserted = static::fillAdminsTable(10);
        $rec->fetchByPrimaryKey($recordsInserted[1]['id']);
        static::assertTrue($rec->existsInDb());
        static::assertFalse($rec->isRelatedRecordAttached('Parent'));
        static::assertFalse($rec->isRelatedRecordAttached('Children'));
        static::assertTrue(isset($rec['Parent']));
        static::assertTrue(isset($rec['Children']));
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertTrue($rec->isRelatedRecordAttached('Children'));
        $rec->reload();
        static::assertFalse($rec->isRelatedRecordAttached('Parent'));
        static::assertFalse($rec->isRelatedRecordAttached('Children'));
        static::assertNotEmpty($rec['Parent']);
        static::assertNotEmpty($rec['Children']);
        static::assertTrue($rec->isRelatedRecordAttached('Parent'));
        static::assertTrue($rec->isRelatedRecordAttached('Children'));
    }
    
    public function testInvalidMagicPropertySetter1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [invalidcolname] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        /** @noinspection PhpUndefinedFieldInspection */
        TestingAdmin::newEmptyRecord()->invalidcolname = 1;
    }
    
    public function testInvalidMagicPropertySetter2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [created_at_as_date] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        TestingAdmin::newEmptyRecord()->created_at_as_date = 1;
    }
    
    public function testInvalidArrayAccessSetter1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [invalidcolname] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        TestingAdmin::newEmptyRecord()['invalidcolname'] = 1;
    }
    
    public function testInvalidArrayAccessSetter2(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("There is no column or relation with name [created_at_as_date] in PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin");
        TestingAdmin::newEmptyRecord()['created_at_as_date'] = 1;
    }
    
    public function testInvalidMagicMethodSetter1(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Magic method 'setInvalidcolumn(\$value, \$isFromDb = false)' is not linked with any column or relation");
        /** @noinspection PhpUndefinedMethodInspection */
        TestingAdmin::newEmptyRecord()
            ->setInvalidcolumn(1);
    }
    
    public function testInvalidMagicMethodSetter2(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Magic method 'setCreatedAtAsDate(\$value, \$isFromDb = false)' is not linked with any column or relation");
        /** @noinspection PhpUndefinedMethodInspection */
        TestingAdmin::newEmptyRecord()
            ->setCreatedAtAsDate(1);
    }
    
    public function testInvalidMagicMethodSetter3(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage("Magic method 'setParentid(\$value, \$isFromDb = false)' is not linked with any column or relation");
        /** @noinspection PhpUndefinedMethodInspection */
        TestingAdmin::newEmptyRecord()
            ->setParentid(1);
    }
    
    public function testInvalidMagicMethodSetter4(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage(
            "Magic method 'setparentid(\$value, \$isFromDb = false)' is forbidden. You can magically call only methods starting with 'set'"
        );
        /** @noinspection PhpUndefinedMethodInspection */
        TestingAdmin::newEmptyRecord()
            ->setparentid(1);
    }
    
    public function testInvalidMagicMethodSetter5(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage(
            "Magic method 'anymethod(\$value, \$isFromDb = false)' is forbidden. You can magically call only methods starting with 'set'"
        );
        /** @noinspection PhpUndefinedMethodInspection */
        TestingAdmin::newEmptyRecord()
            ->anymethod(1);
    }
    
    public function testInvalidMagicMethodSetter6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Magic method 'setId(\$value, \$isFromDb = false)' accepts only 2 arguments, but 3 arguments passed");
        /** @noinspection PhpMethodParametersCountMismatchInspection */
        TestingAdmin::newEmptyRecord()
            ->setId(1, 3, 2);
    }
    
    public function testInvalidMagicMethodSetter7(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "2nd argument for magic method 'setId(\$value, \$isFromDb = false)' must be a boolean and reflects if value received from DB"
        );
        TestingAdmin::newEmptyRecord()
            ->setId(1, 2);
    }
    
    public function testInvalidMagicMethodSetter8(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "1st argument for magic method 'setParent(\$value, \$isFromDb = false)' must be an array or instance of Record class or RecordsSet class"
        );
        TestingAdmin::newEmptyRecord()
            ->setParent(1);
    }
    
    public function testInvalidMagicMethodSetter9(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "1st argument for magic method 'setParent(\$value, \$isFromDb = false)' must be an array or instance of Record class or RecordsSet class"
        );
        TestingAdmin::newEmptyRecord()
            ->setParent($this);
    }
    
    public function testMagicSetterAndMagicSetterMethodAndOffsetSet(): void
    {
        $records = TestingApp::getRecordsForDb('admins', 3);
        unset(
            $records[0]['password'], $records[0]['not_changeable_column'],
            $records[1]['password'], $records[1]['not_changeable_column'],
            $records[2]['password'], $records[2]['not_changeable_column']
        );
        $recForMagickSetterProperty = TestingAdmin::newEmptyRecord();
        $recForMagickSetterMethodFromDb = TestingAdmin::newEmptyRecord();
        $recForMagickSetterMethodNotFromDb = TestingAdmin::newEmptyRecord();
        $recForOffsetSetter = TestingAdmin::newEmptyRecord();
        // columns
        foreach ($records[1] as $name => $value) {
            $recForOffsetSetter->offsetSet($name, $value);
            static::assertTrue($recForOffsetSetter->hasValue($name), "offsetSet: $name");
            static::assertEquals($value, $recForOffsetSetter->getValue($name), "offsetSet: $name");
            if ($name === 'id') {
                static::assertTrue($recForOffsetSetter->isValueFromDb($name), "offsetSet: $name");
            } else {
                static::assertFalse($recForOffsetSetter->isValueFromDb($name), "offsetSet: $name");
            }
            
            $recForMagickSetterProperty->$name = $value;
            static::assertTrue($recForMagickSetterProperty->hasValue($name));
            static::assertEquals($value, $recForMagickSetterProperty->getValue($name));
            if ($name === 'id') {
                static::assertTrue($recForMagickSetterProperty->isValueFromDb($name));
            } else {
                static::assertFalse($recForMagickSetterProperty->isValueFromDb($name));
            }
            
            $setterMethodName = 'set' . StringUtils::toPascalCase($name);
            $recForMagickSetterMethodFromDb->$setterMethodName($value, true);
            static::assertTrue($recForMagickSetterMethodFromDb->hasValue($name));
            static::assertEquals($value, $recForMagickSetterMethodFromDb->getValue($name));
            static::assertTrue($recForMagickSetterMethodFromDb->isValueFromDb($name));
            
            $recForMagickSetterMethodNotFromDb->$setterMethodName($value, $name === 'id');
            static::assertTrue($recForMagickSetterMethodNotFromDb->hasValue($name));
            static::assertEquals($value, $recForMagickSetterMethodNotFromDb->getValue($name));
            if ($name === 'id') {
                static::assertTrue($recForMagickSetterMethodNotFromDb->isValueFromDb($name));
            } else {
                static::assertFalse($recForMagickSetterMethodNotFromDb->isValueFromDb($name));
            }
        }
        // relations
        $recForOffsetSetter->offsetSet('Parent', $records[0]);
        static::assertTrue($recForOffsetSetter->isRelatedRecordAttached('Parent'));
        static::assertEquals(
            $records[0],
            $recForOffsetSetter->getRelatedRecord('Parent')
                ->toArray(array_keys($records[0]))
        );
        static::assertTrue(
            $recForOffsetSetter->getRelatedRecord('Parent')
                ->existsInDb()
        );
        
        $recForMagickSetterProperty->Parent = $records[0];
        static::assertTrue($recForMagickSetterProperty->isRelatedRecordAttached('Parent'));
        static::assertEquals(
            $records[0],
            $recForMagickSetterProperty->getRelatedRecord('Parent')
                ->toArray(array_keys($records[0]))
        );
        static::assertTrue(
            $recForMagickSetterProperty->getRelatedRecord('Parent')
                ->existsInDb()
        );
        
        $recForMagickSetterMethodFromDb->setParent($records[0], true);
        static::assertTrue($recForMagickSetterMethodFromDb->isRelatedRecordAttached('Parent'));
        static::assertEquals(
            $records[0],
            $recForMagickSetterMethodFromDb->getRelatedRecord('Parent')
                ->toArray(array_keys($records[0]))
        );
        static::assertTrue(
            $recForMagickSetterMethodFromDb->getRelatedRecord('Parent')
                ->existsInDb()
        );
        
        $recForMagickSetterMethodNotFromDb->setParent($records[0]);
        static::assertTrue($recForMagickSetterMethodNotFromDb->isRelatedRecordAttached('Parent'));
        static::assertEquals(
            $records[0],
            $recForMagickSetterMethodNotFromDb->getRelatedRecord('Parent')
                ->toArray(array_keys($records[0]))
        );
        static::assertTrue(
            $recForMagickSetterMethodNotFromDb->getRelatedRecord('Parent')
                ->existsInDb()
        );
        
        $recForMagickSetterMethodFromDb->setChildren([$records[0], $records[2]], true);
        static::assertTrue($recForMagickSetterMethodFromDb->isRelatedRecordAttached('Children'));
        static::assertEquals($records[0], $recForMagickSetterMethodFromDb->getRelatedRecord('Children')[0]->toArray(array_keys($records[0])));
        static::assertEquals($records[2], $recForMagickSetterMethodFromDb->getRelatedRecord('Children')[1]->toArray(array_keys($records[2])));
        static::assertTrue($recForMagickSetterMethodFromDb->getRelatedRecord('Children')[0]->existsInDb());
        static::assertTrue($recForMagickSetterMethodFromDb->getRelatedRecord('Children')[1]->existsInDb());
        
        $recForMagickSetterMethodNotFromDb->setChildren([$records[0], $records[2]]);
        static::assertTrue($recForMagickSetterMethodNotFromDb->isRelatedRecordAttached('Children'));
        static::assertEquals($records[0], $recForMagickSetterMethodNotFromDb->getRelatedRecord('Children')[0]->toArray(array_keys($records[0])));
        static::assertEquals($records[2], $recForMagickSetterMethodNotFromDb->getRelatedRecord('Children')[1]->toArray(array_keys($records[2])));
        static::assertTrue($recForMagickSetterMethodNotFromDb->getRelatedRecord('Children')[0]->existsInDb());
        static::assertTrue($recForMagickSetterMethodNotFromDb->getRelatedRecord('Children')[1]->existsInDb());
    }
    
}
