<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Adapter\Postgres;
use PeskyORM\DbExpr;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use PeskyORM\Utils\PdoUtils;
use PeskyORM\Utils\QueryBuilderUtils;
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
    
    protected static function fillTables(): array
    {
        $data = static::getTestDataForAdminsTableInsert();
        static::getValidAdapter()
            ->insertMany('admins', array_keys($data[0]), $data);
        return ['admins' => $data];
    }
    
    protected static function getValidAdapter(): Postgres
    {
        $adapter = TestingApp::getPgsqlConnection();
        $adapter->rememberTransactionQueries = false;
        return $adapter;
    }
    
    public static function getTestDataForAdminsTableInsert(): array
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
    
    public function testInvalidGetDataFromStatement1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unknown processing type [???]");
        $statement = static::getValidAdapter()->query(DbExpr::create('SELECT 1'));
        PdoUtils::getDataFromStatement($statement, '???');
    }
    
    public function testGetDataFromStatement(): void
    {
        $testData = $this->convertTestDataForAdminsTableAssert(static::fillTables()['admins']);
        $statement = static::getValidAdapter()
            ->query(\PeskyORM\DbExpr::create('SELECT * FROM `admins`'));
        static::assertEquals(
            $testData,
            PdoUtils::getDataFromStatement($statement, PdoUtils::FETCH_ALL)
        );
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(
            $testData[0],
            PdoUtils::getDataFromStatement($statement, PdoUtils::FETCH_FIRST)
        );
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(
            Set::extract('/id', $testData),
            PdoUtils::getDataFromStatement($statement, PdoUtils::FETCH_COLUMN)
        );
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(
            $testData[0]['id'],
            PdoUtils::getDataFromStatement($statement, PdoUtils::FETCH_VALUE)
        );
        $statement = static::getValidAdapter()
            ->query(\PeskyORM\DbExpr::create('SELECT * FROM `admins` WHERE `id` < ``0``'));
        static::assertEquals([], PdoUtils::getDataFromStatement($statement, PdoUtils::FETCH_ALL));
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals([], PdoUtils::getDataFromStatement($statement, PdoUtils::FETCH_FIRST));
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals([], PdoUtils::getDataFromStatement($statement, PdoUtils::FETCH_COLUMN));
        $statement->closeCursor();
        $statement->execute();
        static::assertEquals(null, PdoUtils::getDataFromStatement($statement, PdoUtils::FETCH_VALUE));
    }
    
    public function testInvalidAssembleWhereConditionsFromArray1(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("\$glue argument must be \"AND\" or \"OR\"");
        QueryBuilderUtils::assembleWhereConditionsFromArray(static::getValidAdapter(), [], function () {
        }, 'wow');
    }
    
    public function testInvalidAssembleWhereConditionsFromArray3(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "\$conditions argument may contain only objects of class DbExpr or AbstractSelect. Other objects are forbidden. Key: 0"
        );
        QueryBuilderUtils::assembleWhereConditionsFromArray(static::getValidAdapter(), [$this], function () {
        });
    }
    
    public function testInvalidAssembleWhereConditionsFromArray4(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Empty column name detected in \$conditions argument");
        QueryBuilderUtils::assembleWhereConditionsFromArray(static::getValidAdapter(), ['' => 'value'], function () {
        });
    }
    
    public function testInvalidAssembleWhereConditionsFromArray5(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Empty column name detected in \$conditions argument");
        QueryBuilderUtils::assembleWhereConditionsFromArray(static::getValidAdapter(), [' =' => ['value']], function () {
        });
    }
    
    public function testInvalidAssembleWhereConditionsFromArray6(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Empty column name detected in \$conditions argument");
        QueryBuilderUtils::assembleWhereConditionsFromArray(static::getValidAdapter(), ['=' => ['value']], function ($quotedColumn) {
            return $quotedColumn;
        });
    }
    
    public function testInvalidAssembleWhereConditionsFromArray7(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Condition operator [LIKE] does not support list of values");
        QueryBuilderUtils::assembleWhereConditionsFromArray(static::getValidAdapter(), ['col1 LIKE' => ['value']], function ($quotedColumn) {
            return $quotedColumn;
        });
    }
    
    public function testGluesInAssembleWhereConditionsFromArray(): void
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
            QueryBuilderUtils::assembleWhereConditionsFromArray($adapter, [], $columnQuoter)
        );
        static::assertEquals(
            "$col1 = $value1",
            QueryBuilderUtils::assembleWhereConditionsFromArray($adapter, ['col' => 'value'], $columnQuoter)
        );
        static::assertEquals(
            "$col1 = $value1 AND $col2 = $value2",
            QueryBuilderUtils::assembleWhereConditionsFromArray($adapter, ['col' => 'value', 'col2' => 'value2'], $columnQuoter)
        );
        static::assertEquals(
            "$col1 = $value1 OR $col2 = $value2",
            QueryBuilderUtils::assembleWhereConditionsFromArray($adapter, ['col' => 'value', 'col2' => 'value2'], $columnQuoter, 'OR')
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 OR $col2 = $value2)",
            QueryBuilderUtils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'OR' => ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 AND $col2 = $value2)",
            QueryBuilderUtils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 AND $col2 = $value2)",
            QueryBuilderUtils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'AND' => ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 AND $col2 = $value2)",
            QueryBuilderUtils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', ['col' => 'value', 'col2' => 'value2']],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 OR $col2 = $value2) AND $col2 = $value2",
            QueryBuilderUtils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'OR' => ['col' => 'value', 'col2' => 'value2'], 'col2' => 'value2'],
                $columnQuoter
            )
        );
        static::assertEquals(
            "$col1 = $value1 AND ($col1 = $value1 OR ($col1 = $value1 AND $col2 = $value2)) AND $col2 = $value2",
            QueryBuilderUtils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => 'value', 'OR' => ['col' => 'value', ['col' => 'value', 'col2' => 'value2']], 'col2' => 'value2'],
                $columnQuoter
            )
        );
    }
    
    public function testOperatorsInAssembleWhereConditionsFromArray(): void
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
            QueryBuilderUtils::assembleWhereConditionsFromArray(
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
            QueryBuilderUtils::assembleWhereConditionsFromArray(
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
            "$col1->$value2 ?? $value1 AND $col1 ??| array[$value1, $value2] AND $col1 ??& array[$value1, $value2]",
            QueryBuilderUtils::assembleWhereConditionsFromArray(
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
            QueryBuilderUtils::assembleWhereConditionsFromArray(
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
            QueryBuilderUtils::assembleWhereConditionsFromArray(
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
            QueryBuilderUtils::assembleWhereConditionsFromArray(
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
            QueryBuilderUtils::assembleWhereConditionsFromArray(
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
    
    public function testValidationViaAssembleWhereConditionsFromArray(): void
    {
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage("Value [aaa] for column name [test] is invalid");
        QueryBuilderUtils::assembleWhereConditionsFromArray(
            static::getValidAdapter(),
            ['test' => 'aaa'],
            null,
            'AND',
            function ($colName, $value) {
                throw new \UnexpectedValueException("Value [$value] for column name [$colName] is invalid");
            }
        );
    }
    
    public function testDbExprUsageInAssembleWhereConditionsFromArray(): void
    {
        $adapter = static::getValidAdapter();
        $columnQuoter = function ($columnName) use ($adapter) {
            return $adapter->quoteDbEntityName($columnName);
        };
        $col1 = $adapter->quoteDbEntityName('col');
        $value1 = $adapter->quoteValue('value');
        static::assertEquals(
            "($col1 = $value1)",
            QueryBuilderUtils::assembleWhereConditionsFromArray(
                $adapter,
                [\PeskyORM\DbExpr::create('`col` = ``value``')],
                $columnQuoter,
                'AND',
                function ($colName, $value, \PeskyORM\Adapter\DbAdapterInterface $connection) {
                    return $connection->quoteDbExpr($value);
                }
            )
        );
        static::assertEquals(
            "$col1 = ($value1)",
            QueryBuilderUtils::assembleWhereConditionsFromArray(
                $adapter,
                ['col' => \PeskyORM\DbExpr::create('``value``')],
                $columnQuoter
            )
        );
    }
}
