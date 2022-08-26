<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
use PeskyORM\Core\Utils;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use Swayok\Utils\Set;

class UtilsTest extends BaseTestCase
{
    
    public static function setUpBeforeClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    public static function tearDownAfterClass(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    protected function tearDown(): void
    {
        TestingApp::clearTables(static::getValidAdapter());
    }
    
    protected static function fillTables()
    {
        $data = static::getTestDataForAdminsTableInsert();
        static::getValidAdapter()
            ->insertMany('admins', array_keys($data[0]), $data);
        return ['admins' => $data];
    }
    
    protected static function getValidAdapter()
    {
        $adapter = TestingApp::getPgsqlConnection();
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }
    
    public static function getTestDataForAdminsTableInsert()
    {
        return [
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
                'big_data' => 'biiiiiiig data',
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
                'big_data' => 'biiiiiiig data',
            ],
        ];
    }
    
    public function convertTestDataForAdminsTableAssert($data)
    {
        foreach ($data as &$item) {
            $item['id'] = (string)$item['id'];
            $item['is_superadmin'] = (bool)$item['is_superadmin'];
            $item['is_active'] = (bool)$item['is_active'];
            $item['not_changeable_column'] = 'not changable';
        }
        return $data;
    }
    
    public function testInvalidGetDataFromStatement1()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown processing type [???]");
        $statement = static::getValidAdapter()->query(DbExpr::create('SELECT 1'));
        Utils::getDataFromStatement($statement, '???');
    }
    
    public function testGetDataFromStatement()
    {
        $testData = $this->convertTestDataForAdminsTableAssert(static::fillTables()['admins']);
        $statement = static::getValidAdapter()
            ->query(DbExpr::create('SELECT * FROM `admins`'));
        static::assertEquals(
            $testData,
            Utils::getDataFromStatement($statement, Utils::FETCH_ALL)
        );
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(
            $testData[0],
            Utils::getDataFromStatement($statement, Utils::FETCH_FIRST)
        );
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(
            Set::extract('/id', $testData),
            Utils::getDataFromStatement($statement, Utils::FETCH_COLUMN)
        );
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(
            $testData[0]['id'],
            Utils::getDataFromStatement($statement, Utils::FETCH_VALUE)
        );
        $statement = static::getValidAdapter()
            ->query(DbExpr::create('SELECT * FROM `admins` WHERE `id` < ``0``'));
        static::assertEquals([], Utils::getDataFromStatement($statement, Utils::FETCH_ALL));
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals([], Utils::getDataFromStatement($statement, Utils::FETCH_FIRST));
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals([], Utils::getDataFromStatement($statement, Utils::FETCH_COLUMN));
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(null, Utils::getDataFromStatement($statement, Utils::FETCH_VALUE));
    }
    
    public function testInvalidAssembleWhereConditionsFromArray1()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$glue argument must be \"AND\" or \"OR\"");
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), [], function () {
        }, 'wow');
    }
    
    public function testInvalidAssembleWhereConditionsFromArray3()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "\$conditions argument may contain only objects of class DbExpr or AbstractSelect. Other objects are forbidden. Key: 0"
        );
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), [$this], function () {
        });
    }
    
    public function testInvalidAssembleWhereConditionsFromArray4()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Empty column name detected in \$conditions argument");
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), ['' => 'value'], function () {
        });
    }
    
    public function testInvalidAssembleWhereConditionsFromArray5()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Empty column name detected in \$conditions argument");
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), [' =' => ['value']], function () {
        });
    }
    
    public function testInvalidAssembleWhereConditionsFromArray6()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Empty column name detected in \$conditions argument");
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), ['=' => ['value']], function () {
        });
    }
    
    public function testInvalidAssembleWhereConditionsFromArray7()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition operator [LIKE] does not support list of values");
        Utils::assembleWhereConditionsFromArray(static::getValidAdapter(), ['col1 LIKE' => ['value']], function () {
        });
    }
    
    public function testGluesInAssembleWhereConditionsFromArray()
    {
        $adapter = static::getValidAdapter();
        $columnQuoter = function ($columnName) use ($adapter) {
            return $adapter->quoteDbEntityName($columnName);
        };
        $col1 = $adapter->quoteDbEntityName('col');
        $col2 = $adapter->quoteDbEntityName('col2');
        $value1 = $adapter->quoteValue('value');
        $value2 = $adapter->quoteValue('value2');
        static::assertEquals(
            '',
            Utils::assembleWhereConditionsFromArray($adapter, [], $columnQuoter)
        );
        static::assertEquals(
            "$col1 = $value1",
            Utils::assembleWhereConditionsFromArray($adapter, ['col' => 'value'], $columnQuoter)
        );
        static::assertEquals(
            "$col1 = $value1 AND $col2 = $value2",
            Utils::assembleWhereConditionsFromArray($adapter, ['col' => 'value', 'col2' => 'value2'], $columnQuoter)
        );
        static::assertEquals(
            "$col1 = $value1 OR $col2 = $value2",
            Utils::assembleWhereConditionsFromArray($adapter, ['col' => 'value', 'col2' => 'value2'], $columnQuoter, 'OR')
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 OR $col2 = $value2)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'OR' => ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 AND $col2 = $value2)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 AND $col2 = $value2)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'AND' => ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 AND $col2 = $value2)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 OR $col2 = $value2) AND $col2 = $value2",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'OR' => ['col' => 'value', 'col2' => 'value2'], 'col2' => 'value2'],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 OR ($col1 = $value1 AND $col2 = $value2)) AND $col2 = $value2",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'OR' => ['col' => 'value', ['col' => 'value', 'col2' => 'value2']], 'col2' => 'value2'],
                $columnQuoter
            )
        );
    }
    
    public function testOperatorsInAssembleWhereConditionsFromArray()
    {
        $adapter = static::getValidAdapter();
        $columnQuoter = function ($columnName) use ($adapter) {
            return $adapter->quoteDbEntityName($columnName);
        };
        $col1 = $adapter->quoteDbEntityName('col');
        $value1 = $adapter->quoteValue('value');
        $value2 = $adapter->quoteValue('value2');
        static::assertEquals(
            "$col1 = $value1 AND $col1 != $value1 AND $col1 = $value1 AND $col1 != $value1 AND $col1 != $value1",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col =' => 'value',
                    'col !=' => 'value',
                    'col is' => 'value',
                    'col not' => 'value',
                    'col is not' => 'value',
                ],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 < $value1 AND $col1 <= $value1 AND $col1 > $value1 AND $col1 >= $value1",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col <' => 'value',
                    'col <=' => 'value',
                    'col >' => 'value',
                    'col >=' => 'value',
                ],
                $columnQuoter
            )
        );
        static::assertEquals(
            "jsonb_exists($col1->$value2, $value1) AND jsonb_exists_any($col1, array[$value1, $value2]) AND jsonb_exists_all($col1, array[$value1, $value2])",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col->value2 ?' => 'value',
                    'col ?|' => ['value', 'value2'],
                    'col ?&' => ['value', 'value2'],
                ],
                $columnQuoter
            )
        );
        $jsonQuoted = $adapter->quoteValue(json_encode(['value' => 'value2']));
        static::assertEquals(
            "$col1 @> $jsonQuoted::jsonb AND $col1 <@ $jsonQuoted::jsonb",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col @>' => ['value' => 'value2'],
                    'col <@' => json_encode(['value' => 'value2']),
                ],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 ~ $value1 AND $col1 !~ $value1 AND $col1 ~* $value1 AND $col1 !~* $value1",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col ~' => 'value',
                    'col !~' => 'value',
                    'col ~*' => 'value',
                    'col !~*' => 'value',
                ],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 LIKE $value1 AND $col1 NOT LIKE $value1",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col LIKE' => 'value',
                    'col NOT LIKE ' => 'value',
                ],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 IS NULL AND $col1 IS NOT NULL AND $col1 IS NULL AND $col1 IS NOT NULL AND $col1 IS NOT NULL",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [
                    'col =' => null,
                    'col !=' => null,
                    'col IS' => null,
                    'col NOT' => null,
                    'col IS NOT' => null,
                ],
                $columnQuoter
            )
        );
    }
    
    public function testValidationViaAssembleWhereConditionsFromArray()
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Value [aaa] for column name [test] is invalid");
        Utils::assembleWhereConditionsFromArray(
            static::getValidAdapter(),
            ['test' => 'aaa'],
            null,
            'AND',
            function ($colName, $value) {
                throw new \UnexpectedValueException("Value [$value] for column name [$colName] is invalid");
            }
        );
    }
    
    public function testDbExprUsageInAssembleWhereConditionsFromArray()
    {
        $adapter = static::getValidAdapter();
        $columnQuoter = function ($columnName) use ($adapter) {
            return $adapter->quoteDbEntityName($columnName);
        };
        $col1 = $adapter->quoteDbEntityName('col');
        $value1 = $adapter->quoteValue('value');
        static::assertEquals(
            "($col1 = $value1)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                [DbExpr::create('`col` = ``value``')],
                $columnQuoter,
                'AND',
                function ($colName, $value, DbAdapterInterface $connection) {
                    return $connection->quoteDbExpr($value);
                }
            )
        );
        static::assertEquals(
            "$col1 = ($value1)",
            Utils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => DbExpr::create('``value``')],
                $columnQuoter
            )
        );
    }
}
