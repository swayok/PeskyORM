<?php

declare(strict_types=1);

namespace PeskyORM\Tests\Orm;

use PeskyORM\ORM\TableStructure\TableColumnFactory;
use PeskyORM\TableDescription\TableDescribersRegistry;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\ClassBuilderTestingClasses\TestingClassBuilder;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;
use PeskyORM\Tests\PeskyORMTest\TestingApp;
use PeskyORM\Tests\PeskyORMTest\TestingBaseTable;

class ClassBuilderTest extends BaseTestCase
{
    protected function getBuilder(string $tableName = 'admins'): TestingClassBuilder
    {
        $tableDescription = TableDescribersRegistry::describeTable(
            TestingApp::getPgsqlConnection(),
            $tableName
        );
        return new TestingClassBuilder(
            $tableDescription,
            new TableColumnFactory(),
            'PeskyORM\\Tests\\PeskyORMTest\\ClassBuilderTestingClasses',
        );
    }
    
    public function testTableClassBuilding(): void
    {
        $builder = $this->getBuilder();

        $expected = file_get_contents(
            __DIR__ . '/../PeskyORMTest/ClassBuilderTestingClasses/BuilderTesting1AdminsTable.php'
        );
        $builder->setClassesPrefix('BuilderTesting1');
        $actual = $builder->buildTableClass();
        static::assertEquals(
            $this->cleanFileContents($expected),
            $this->cleanFileContents($actual),
            $actual
        );
        $expected = file_get_contents(
            __DIR__ . '/../PeskyORMTest/ClassBuilderTestingClasses/BuilderTesting2AdminsTable.php'
        );
        $builder->setClassesPrefix('BuilderTesting2');
        $actual = $builder->buildTableClass(TestingBaseTable::class);
        static::assertEquals(
            $this->cleanFileContents($expected),
            $this->cleanFileContents($actual),
            $actual
        );
    }

    public function testRecordableClassBuilding(): void
    {
        $builder = $this->getBuilder();

        $expected = file_get_contents(
            __DIR__ . '/../PeskyORMTest/ClassBuilderTestingClasses/BuilderTesting1Admin.php'
        );
        $builder->setClassesPrefix('BuilderTesting1');
        $actual = $builder->buildRecordClass();
        static::assertEquals(
            $this->cleanFileContents($expected),
            $this->cleanFileContents($actual),
            $actual
        );
        $expected = file_get_contents(
            __DIR__ . '/../PeskyORMTest/ClassBuilderTestingClasses/BuilderTesting2Admin.php'
        );
        $builder->setClassesPrefix('BuilderTesting2');
        $actual = $builder->buildRecordClass(TestingAdmin::class);
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
        $actual = $builder->buildStructureClass();
        static::assertEquals(
            $this->cleanFileContents($expected),
            $this->cleanFileContents($actual),
            $actual
        );
        $expected = file_get_contents(
            __DIR__ . '/../PeskyORMTest/ClassBuilderTestingClasses/BuilderTesting2AdminsTableStructure.php'
        );
        $actual = $builder->buildStructureClass(TestingAdminsTableStructure::class);
        static::assertEquals(
            $this->cleanFileContents($expected),
            $this->cleanFileContents($actual),
            $actual
        );
    }

    private function cleanFileContents(string $text): string
    {
        return preg_replace(
            ["%<\?php\s*/\*\* @noinspection ALL \*/%s", "%[\r\n\t]+%"],
            ['<?php', ''],
            $text
        );
    }
}
