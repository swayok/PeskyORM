<?php

declare(strict_types=1);

namespace PeskyORM\Tests\TableColumns;

use PeskyORM\Exception\TableColumnConfigException;
use PeskyORM\ORM\Record\RecordValueContainerInterface;
use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\TableColumn\Column\VirtualColumn;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Tests\PeskyORMTest\BaseTestCase;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdmin;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTable;

class VirtualColumnTest extends BaseTestCase
{
    public function testVirtualColumn(): void
    {
        $column = new VirtualColumn(
            'virtual',
            static function (
                RecordValueContainerInterface $valueContainer,
                bool $allowDefaultValue
            ): bool {
                $record = $valueContainer->getRecord();
                return (
                    $record->hasValue('login', $allowDefaultValue)
                    && $record->hasValue('email', $allowDefaultValue)
                );
            },
            static function (RecordValueContainerInterface $valueContainer): string {
                $record = $valueContainer->getRecord();
                return $record->getValue('login') . ': ' . $record->getValue('email');
            }
        );

        static::assertEquals(TableColumnDataType::VIRTUAL, $column->getDataType());
        static::assertFalse($column->isReal());
        static::assertTrue($column->isNullableValues());
        static::assertTrue($column->isReadonly());
        static::assertFalse($column->isAutoUpdatingValues());
        static::assertFalse($column->isForeignKey());
        static::assertFalse($column->isPrimaryKey());
        static::assertFalse($column->hasDefaultValue());
        // empty record
        $container = $column->getNewRecordValueContainer(new TestingAdmin());
        static::assertFalse($column->hasValue($container, false));
        static::assertFalse($column->hasValue($container, true));
        // filled record
        $data = [
            'id' => null,
            'parent_id' => 1,
            'login' => 'test',
            'email' => 'email@test.com',
            'password' => 'test',
        ];
        $record = TestingAdmin::fromArray($data);
        $container = $column->getNewRecordValueContainer($record);
        static::assertTrue($column->hasValue($container, false));
        static::assertTrue($column->hasValue($container, true));
        static::assertEquals(
            $data['login'] . ': ' . $data['email'],
            $column->getValue($container, null)
        );
    }

    public function testBadMethodCall1(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Column .*'virtual'.* is virtual%"
        );
        $column = new VirtualColumn(
            'virtual',
            static function (): bool {
                return false;
            },
            static function (): mixed {
                return null;
            }
        );
        $column->getAutoUpdateForAValue(new TestingAdmin());
    }

    public function testBadMethodCall2(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Column .*'virtual'.* is virtual%"
        );
        $column = new VirtualColumn(
            'virtual',
            static function (): bool {
                return false;
            },
            static function (): mixed {
                return null;
            }
        );
        $column->getRelation('Test');
    }

    public function testBadMethodCall3(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Column .*'virtual'.* is virtual%"
        );
        $column = new VirtualColumn(
            'virtual',
            static function (): bool {
                return false;
            },
            static function (): mixed {
                return null;
            }
        );
        $column->addRelation(
            new Relation(
                'id',
                Relation::HAS_MANY,
                TestingAdminsTable::getInstance(),
                'parent_id',
                'Test'
            )
        );
    }

    public function testBadMethodCall4(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Column .*'virtual'.* is virtual%"
        );
        $column = new VirtualColumn(
            'virtual',
            static function (): bool {
                return false;
            },
            static function (): mixed {
                return null;
            }
        );
        $column->validateValue('Test');
    }

    public function testBadMethodCall5(): void
    {
        $this->expectException(TableColumnConfigException::class);
        $this->expectExceptionMessageMatches(
            "%Column .*'virtual'.* is virtual%"
        );
        $column = new VirtualColumn(
            'virtual',
            static function (): bool {
                return false;
            },
            static function (): mixed {
                return null;
            }
        );
        $column->setValue(
            $column->getNewRecordValueContainer(new TestingAdmin()),
            'Test',
            false,
            false
        );
    }
}