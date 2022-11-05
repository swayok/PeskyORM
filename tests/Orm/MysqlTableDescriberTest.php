<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\Core\DbExpr;
use PeskyORM\ORM\Column;
use PeskyORM\TableDescription\DescribeTable;
use PeskyORM\TableDescription\TableDescribers\MysqlTableDescriber;
use PeskyORM\TableDescription\TableDescribers\PostgresTableDescriber;
use PeskyORM\TableDescription\TableDescription;
use PeskyORM\Tests\PeskyORMTest\Adapter\MysqlTesting;
use PeskyORM\Tests\PeskyORMTest\Adapter\OtherAdapterTesting;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

require_once __DIR__ . '/PostgresTableDescriberTest.php';

class MysqlTableDescriberTest extends PostgresTableDescriberTest
{
    
    /**
     * @return MysqlTesting
     */
    protected static function getValidAdapter(): DbAdapterInterface
    {
        return TestingApp::getMysqlConnection();
    }

    public function testDescribeTable(): void
    {
        $adapter = self::getValidAdapter();
        static::assertInstanceOf(MysqlTableDescriber::class, DescribeTable::getDescriber($adapter));
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(TableDescription::class, DescribeTable::getTableDescription($adapter, 'settings'));
        // set custom describer
        $otherAdapter = new OtherAdapterTesting(new PostgresConfig('test', 'test', 'test'));
        DescribeTable::registerDescriber($otherAdapter::class, PostgresTableDescriber::class);
        static::assertInstanceOf(PostgresTableDescriber::class, DescribeTable::getDescriber($otherAdapter));
    }

    /**
     * @covers MysqlTableDescriber::extractLimitAndPrecisionForColumnDescription()
     */
    public function testExtractLimitAndPrecisionForColumnDescription(): void
    {
        $describer = new MysqlTableDescriber(static::getValidAdapter());
        $extractLimitAndPrecisionForColumnDescription = $this
            ->getMethodReflection($describer, 'extractLimitAndPrecisionForColumnDescription')
            ->getClosure($describer);

        static::assertEquals(
            ['limit' => null, 'precision' => null],
            $extractLimitAndPrecisionForColumnDescription('timestamp')
        );
        static::assertEquals(
            ['limit' => 11, 'precision' => null],
            $extractLimitAndPrecisionForColumnDescription('int(11)')
        );
        static::assertEquals(
            ['limit' => 200, 'precision' => null],
            $extractLimitAndPrecisionForColumnDescription('varchar(200)')
        );
        static::assertEquals(
            ['limit' => 8, 'precision' => 2],
            $extractLimitAndPrecisionForColumnDescription('float(8,2)')
        );
    }

    /**
     * @covers MysqlTableDescriber::cleanDefaultValueForColumnDescription()
     */
    public function testCleanDefaultValueForColumnDescription(): void
    {
        $describer = new MysqlTableDescriber(static::getValidAdapter());
        $cleanDefaultValueForColumnDescription = $this
            ->getMethodReflection($describer, 'cleanDefaultValueForColumnDescription')
            ->getClosure($describer);

        static::assertEquals(
            '',
            $cleanDefaultValueForColumnDescription('')
        );
        static::assertEquals(
            ' ',
            $cleanDefaultValueForColumnDescription(' ')
        );
        static::assertEquals(
            'a',
            $cleanDefaultValueForColumnDescription('a')
        );
        static::assertEquals(
            null,
            $cleanDefaultValueForColumnDescription(null)
        );
        static::assertEquals(
            true,
            $cleanDefaultValueForColumnDescription('true')
        );
        static::assertEquals(
            false,
            $cleanDefaultValueForColumnDescription('false')
        );
        static::assertEquals(
            11,
            $cleanDefaultValueForColumnDescription('11')
        );
        static::assertEquals(
            11.1,
            $cleanDefaultValueForColumnDescription('11.1')
        );
        static::assertEquals(
            "'11.1'",
            $cleanDefaultValueForColumnDescription("'11.1'")
        );
        static::assertEquals(
            DbExpr::create('NOW()'),
            $cleanDefaultValueForColumnDescription('CURRENT_TIMESTAMP')
        );
        static::assertEquals(
            "'",
            $cleanDefaultValueForColumnDescription("'")
        );
        static::assertEquals(
            "test'quote",
            $cleanDefaultValueForColumnDescription("test'quote")
        );
        static::assertEquals(
            "test'",
            $cleanDefaultValueForColumnDescription("test'")
        );
        static::assertEquals(
            "'quote",
            $cleanDefaultValueForColumnDescription("'quote")
        );
        static::assertEquals(
            "'quote'",
            $cleanDefaultValueForColumnDescription("'quote'")
        );
        static::assertEquals(
            "'quote'test ' asd'",
            $cleanDefaultValueForColumnDescription("'quote'test ' asd'")
        );
    }
    
    public function testGetTableDescription(): void
    {
        $describer = new MysqlTableDescriber(static::getValidAdapter());

        $description = $describer->getTableDescription('settings');
        static::assertInstanceOf(TableDescription::class, $description);
        static::assertEquals('settings', $description->getTableName());
        static::assertEquals(null, $description->getDbSchema());
        static::assertCount(3, $description->getColumns());
        
        $idCol = $description->getColumn('id');
        static::assertEquals('id', $idCol->getName());
        static::assertEquals('int(11)', $idCol->getDbType());
        static::assertEquals(Column::TYPE_INT, $idCol->getOrmType());
        static::assertEquals(null, $idCol->getDefault());
        static::assertEquals(null, $idCol->getNumberPrecision());
        static::assertEquals(11, $idCol->getLimit());
        static::assertTrue($idCol->isPrimaryKey());
        static::assertFalse($idCol->isUnique());
        static::assertFalse($idCol->isForeignKey());
        static::assertFalse($idCol->isNullable());
        
        $keyCol = $description->getColumn('key');
        static::assertEquals('key', $keyCol->getName());
        static::assertEquals('varchar(100)', $keyCol->getDbType());
        static::assertEquals(Column::TYPE_STRING, $keyCol->getOrmType());
        static::assertEquals(null, $keyCol->getDefault());
        static::assertEquals(null, $keyCol->getNumberPrecision());
        static::assertEquals(100, $keyCol->getLimit());
        static::assertFalse($keyCol->isPrimaryKey());
        static::assertTrue($keyCol->isUnique());
        static::assertFalse($keyCol->isForeignKey());
        static::assertFalse($keyCol->isNullable());
        
        $valueCol = $description->getColumn('value');
        static::assertEquals('value', $valueCol->getName());
        static::assertEquals('text', $valueCol->getDbType());
        static::assertEquals(Column::TYPE_TEXT, $valueCol->getOrmType());
        static::assertEquals(null, $valueCol->getDefault());
        static::assertEquals(null, $valueCol->getNumberPrecision());
        static::assertEquals(null, $valueCol->getLimit());
        static::assertFalse($valueCol->isPrimaryKey());
        static::assertFalse($valueCol->isUnique());
        static::assertFalse($valueCol->isForeignKey());
        static::assertFalse($valueCol->isNullable());
        
        $description = $describer->getTableDescription('admins');
        static::assertEquals('admins', $description->getTableName());
        static::assertEquals(null, $description->getDbSchema());
        static::assertCount(17, $description->getColumns());
        static::assertTrue($description->getColumn('login')->isUnique());
        static::assertTrue($description->getColumn('email')->isUnique());
        static::assertFalse($description->getColumn('parent_id')->isForeignKey()); //< description does not show this
        static::assertTrue($description->getColumn('remember_token')->isNullable());
        static::assertEquals(DbExpr::create('NOW()'), $description->getColumn('created_at')->getDefault());
    }
}