<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\ORM\ClassBuilder\ClassBuilder;
use PeskyORM\ORM\TableStructure\TableColumnFactory;
use PeskyORM\TableDescription\TableDescriptionFacade;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use PeskyORM\Tests\PeskyORMTest\TestingBaseTable;
use PeskyORM\Utils\ServiceContainer;

class ClassBuilderTest extends BaseTestCase
{
    protected function getBuilder(string $tableName = 'admins'): ClassBuilder
    {
        $tableDescription = TableDescriptionFacade::describeTable(
            TestingApp::getPgsqlConnection(),
            $tableName
        );
        return new ClassBuilder(
            $tableDescription,
            ServiceContainer::getInstance()->make(TableColumnFactory::class),
            'PeskyORM\\Tests\\PeskyORMTest\\ClassBuilderTestingClasses',
        );
    }

    public function testTableClassBuilding(): void
    {
        $builder = $this->getBuilder();

        $expected = file_get_contents(
            __DIR__ . '/../PeskyORMTest/ClassBuilderTestingClasses/BuilderTesting1AdminsTable.php'
        );
        $actual = $builder->buildTableClass(
            null,
            'BuilderTesting1AdminsTable',
            'BuilderTesting1AdminsTableStructure',
            'BuilderTesting1Admin',
        );
        static::assertEquals(
            $this->cleanFileContents($expected),
            $this->cleanFileContents($actual),
            $actual
        );
        $expected = file_get_contents(
            __DIR__ . '/../PeskyORMTest/ClassBuilderTestingClasses/BuilderTesting2AdminsTable.php'
        );
        $actual = $builder->buildTableClass(
            TestingBaseTable::class,
            'BuilderTesting2AdminsTable',
            'BuilderTesting2AdminsTableStructure',
            'BuilderTesting2Admin',
        );
        static::assertEquals(
            $this->cleanFileContents($expected),
            $this->cleanFileContents($actual),
            $actual
        );
    }

    public function testRecordClassBuilding(): void
    {
        $builder = $this->getBuilder();

        $expected = file_get_contents(
            __DIR__ . '/../PeskyORMTest/ClassBuilderTestingClasses/BuilderTesting1Admin.php'
        );
        $actual = $builder->buildRecordClass(
            null,
            'BuilderTesting1Admin',
            'BuilderTesting1AdminsTable',
        );
        static::assertEquals(
            $this->cleanFileContents($expected),
            $this->cleanFileContents($actual),
            $actual
        );
        $expected = file_get_contents(
            __DIR__ . '/../PeskyORMTest/ClassBuilderTestingClasses/BuilderTesting2Admin.php'
        );
        $actual = $builder->buildRecordClass(
            TestingAdmin::class,
            'BuilderTesting2Admin',
            'BuilderTesting2AdminsTable',
        );
        static::assertEquals(
            $this->cleanFileContents($expected),
            $this->cleanFileContents($actual),
            $actual
        );
    }

    public function testDbStructureClassBuilder(): void
    {
        $builder = $this->getBuilder();

        $expected = file_get_contents(
            __DIR__ . '/../PeskyORMTest/ClassBuilderTestingClasses/BuilderTesting1AdminsTableStructure.php'
        );
        $actual = $builder->buildStructureClass(
            null,
            'BuilderTesting1AdminsTableStructure',
        );
        static::assertEquals(
            $this->cleanFileContents($expected),
            $this->cleanFileContents($actual),
            $actual
        );
        $expected = file_get_contents(
            __DIR__ . '/../PeskyORMTest/ClassBuilderTestingClasses/BuilderTesting2AdminsTableStructure.php'
        );
        $actual = $builder->buildStructureClass(
            TestingAdminsTableStructure::class,
            'BuilderTesting2AdminsTableStructure',
        );
        static::assertEquals(
            $this->cleanFileContents($expected),
            $this->cleanFileContents($actual),
            $actual
        );
    }

    private function cleanFileContents(string $text): string
    {
        return preg_replace(
            ["%<\?php\s*/\*\* @noinspection .*? \*/\s*%s", "%[\r\n\t]+%"],
            ['<?php', ''],
            $text
        );
    }
}
