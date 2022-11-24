<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Core;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\Config\Connection\MysqlConfig;
use PeskyORM\Config\Connection\PostgresConfig;
use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumn;
use PeskyORM\TableDescription\TableDescribers\MysqlTableDescriber;
use PeskyORM\TableDescription\TableDescribers\PostgresTableDescriber;
use PeskyORM\TableDescription\TableDescribersRegistry;
use PeskyORM\TableDescription\TableDescription;
use PeskyORM\Tests\PeskyORMTest\Adapter\OtherAdapterTesting;
use PeskyORM\Tests\PeskyORMTest\Adapter\OtherAdapterTesting2;
use PeskyORM\Tests\PeskyORMTest\Adapter\PostgresTesting;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingApp;

class PostgresTableDescriberTest extends BaseTestCase
{

    /**
     * @return PostgresTesting
     */
    protected static function getValidAdapter(): DbAdapterInterface
    {
        return TestingApp::getPgsqlConnection();
    }

    public function testInvalidAdapterForDescribeTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('There are no table describer for');
        $adapter = new OtherAdapterTesting2(new MysqlConfig('test', 'test', 'test'));
        TableDescribersRegistry::getDescriber($adapter);
    }

    public function testDescribeTable(): void
    {
        $adapter = self::getValidAdapter();
        static::assertInstanceOf(PostgresTableDescriber::class, TableDescribersRegistry::getDescriber($adapter));
        /** @noinspection UnnecessaryAssertionInspection */
        static::assertInstanceOf(TableDescription::class, TableDescribersRegistry::describeTable($adapter, 'settings'));

        // set custom describer
        $otherAdapter = new OtherAdapterTesting(new PostgresConfig('test', 'test', 'test'));
        TableDescribersRegistry::registerDescriber($otherAdapter::class, MysqlTableDescriber::class);
        static::assertInstanceOf(MysqlTableDescriber::class, TableDescribersRegistry::getDescriber($otherAdapter));
    }

    /**
     * @covers PostgresTableDescriber::extractLimitAndPrecisionForColumnDescription()
     */
    public function testExtractLimitAndPrecisionForColumnDescription(): void
    {
        $describer = new PostgresTableDescriber(static::getValidAdapter());

        $extractLimitAndPrecisionForColumnDescription = $this
            ->getMethodReflection($describer, 'extractLimitAndPrecisionForColumnDescription')
            ->getClosure($describer);

        static::assertEquals(
            ['limit' => null, 'precision' => null],
            $extractLimitAndPrecisionForColumnDescription('integer')
        );
        static::assertEquals(
            ['limit' => 200, 'precision' => null],
            $extractLimitAndPrecisionForColumnDescription('character varying(200)')
        );
        static::assertEquals(
            ['limit' => 8, 'precision' => 2],
            $extractLimitAndPrecisionForColumnDescription('numeric(8,2)')
        );
    }

    /**
     * @covers PostgresTableDescriber::cleanDefaultValueForColumnDescription()
     */
    public function testCleanDefaultValueForColumnDescription(): void
    {
        $describer = new PostgresTableDescriber(static::getValidAdapter());

        $cleanDefaultValueForColumnDescription = $this
            ->getMethodReflection($describer, 'cleanDefaultValueForColumnDescription')
            ->getClosure($describer);

        static::assertEquals(
            '',
            $cleanDefaultValueForColumnDescription("''::character varying")
        );
        static::assertEquals(
            ' ',
            $cleanDefaultValueForColumnDescription("' '::text")
        );
        static::assertEquals(
            'a',
            $cleanDefaultValueForColumnDescription("'a'::bpchar")
        );
        static::assertEquals(
            '{}',
            $cleanDefaultValueForColumnDescription("'{}'::jsonb")
        );
        static::assertEquals(
            null,
            $cleanDefaultValueForColumnDescription('')
        );
        static::assertEquals(
            null,
            $cleanDefaultValueForColumnDescription(null)
        );
        static::assertEquals(
            null,
            $cleanDefaultValueForColumnDescription('NULL::character varying')
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
            11.1,
            $cleanDefaultValueForColumnDescription("'11.1'")
        );
        static::assertEquals(
            DbExpr::create("'somecode'::text + NOW()::text + INTERVAL '1 day'::text"),
            $cleanDefaultValueForColumnDescription("'somecode'::text + NOW()::text + INTERVAL '1 day'::text")
        );
        static::assertEquals(
            "'",
            $cleanDefaultValueForColumnDescription("''''::text")
        );
        static::assertEquals(
            "test'quote",
            $cleanDefaultValueForColumnDescription("'test''quote'::text")
        );
        static::assertEquals(
            "test'",
            $cleanDefaultValueForColumnDescription("'test'''::text")
        );
        static::assertEquals(
            "'quote",
            $cleanDefaultValueForColumnDescription("'''quote'::text")
        );
        static::assertEquals(
            "'quote'",
            $cleanDefaultValueForColumnDescription("'''quote'''::text")
        );
        static::assertEquals(
            "'quote'test ' asd'",
            $cleanDefaultValueForColumnDescription("'''quote''test '' asd'''::text")
        );
        static::assertEquals(
            "'quote'test ' asd'",
            $cleanDefaultValueForColumnDescription("'''quote''test '' asd'''")
        );
        static::assertEquals(
            DbExpr::create('NOW()'),
            $cleanDefaultValueForColumnDescription('NOW()')
        );
    }

    public function testGetTableDescription(): void
    {
        $describer = new PostgresTableDescriber(static::getValidAdapter());

        $description = $describer->getTableDescription('settings');
        static::assertInstanceOf(TableDescription::class, $description);
        static::assertEquals('settings', $description->getTableName());
        static::assertEquals('public', $description->getDbSchema());
        static::assertCount(3, $description->getColumns());

        $idCol = $description->getColumn('id');
        static::assertEquals('id', $idCol->getName());
        static::assertEquals('int4', $idCol->getDbType());
        static::assertEquals(TableColumn::TYPE_INT, $idCol->getOrmType());
        static::assertEquals(DbExpr::create('nextval(\'settings_id_seq\'::regclass)'), $idCol->getDefault());
        static::assertEquals(null, $idCol->getNumberPrecision());
        static::assertEquals(null, $idCol->getLimit());
        static::assertTrue($idCol->isPrimaryKey());
        static::assertFalse($idCol->isUnique());
        static::assertFalse($idCol->isForeignKey());
        static::assertFalse($idCol->isNullable());

        $keyCol = $description->getColumn('key');
        static::assertEquals('key', $keyCol->getName());
        static::assertEquals('varchar', $keyCol->getDbType());
        static::assertEquals(TableColumn::TYPE_STRING, $keyCol->getOrmType());
        static::assertEquals(null, $keyCol->getDefault());
        static::assertEquals(null, $keyCol->getNumberPrecision());
        static::assertEquals(100, $keyCol->getLimit());
        static::assertFalse($keyCol->isPrimaryKey());
        static::assertTrue($keyCol->isUnique());
        static::assertFalse($keyCol->isForeignKey());
        static::assertFalse($keyCol->isNullable());

        $valueCol = $description->getColumn('value');
        static::assertEquals('value', $valueCol->getName());
        static::assertEquals('jsonb', $valueCol->getDbType());
        static::assertEquals(TableColumn::TYPE_JSONB, $valueCol->getOrmType());
        static::assertEquals('{}', $valueCol->getDefault());
        static::assertEquals(null, $valueCol->getNumberPrecision());
        static::assertEquals(null, $valueCol->getLimit());
        static::assertFalse($valueCol->isPrimaryKey());
        static::assertFalse($valueCol->isUnique());
        static::assertFalse($valueCol->isForeignKey());
        static::assertFalse($valueCol->isNullable());

        $description = $describer->getTableDescription('admins');
        static::assertEquals('admins', $description->getTableName());
        static::assertEquals('public', $description->getDbSchema());
        static::assertCount(17, $description->getColumns());
        static::assertTrue($description->getColumn('login')->isUnique());
        static::assertTrue($description->getColumn('email')->isUnique());
        static::assertTrue($description->getColumn('parent_id')->isForeignKey());
        static::assertTrue($description->getColumn('remember_token')->isNullable());
    }

}