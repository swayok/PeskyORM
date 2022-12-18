<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\Relation;
use PeskyORM\ORM\TableStructure\TableColumn\Column\BooleanColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\EmailColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IdColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IntegerColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IpV4AddressColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\PasswordColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\StringColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TextColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TimestampColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\VirtualColumn;
use PeskyORM\ORM\TableStructure\TableStructure;
use PeskyORM\Tests\PeskyORMTest\TableColumn\TestColumnWithAfterSave;
use PeskyORM\Tests\PeskyORMTest\TableColumn\TestImageColumn;
use PeskyORM\Tests\PeskyORMTest\TableColumn\TestVirtualColumnWithAfterSave;

class TestingAdmins3TableStructure extends TableStructure
{
    public function getTableName(): string
    {
        return 'admins';
    }

    protected function registerColumns(): void
    {
        $this->addColumn(
            new IdColumn()
        );
        $this->addColumn(
            (new IntegerColumn('parent_id'))
                ->allowsNullValues()
        );
        $this->addColumn(
            (new TestColumnWithAfterSave('login'))
                ->trimsValues()
                ->convertsEmptyStringValuesToNull()
        );
        $this->addColumn(
            new PasswordColumn('password')
        );
        $this->addColumn(
            (new TimestampColumn('created_at'))
                ->setDefaultValue(DbExpr::create('NOW()'))
        );
        $this->addColumn(
            (new TimestampColumn('updated_at'))
                ->setDefaultValue(DbExpr::create('NOW()'))
        );
        $this->addColumn(
            (new StringColumn('remember_token'))
                ->allowsNullValues()
                ->trimsValues()
                ->convertsEmptyStringValuesToNull()
        );
        $this->addColumn(
            (new BooleanColumn('is_superadmin'))
                ->setDefaultValue(false)
        );
        $this->addColumn(
            (new StringColumn('language'))
                ->convertsEmptyStringValuesToNull()
                ->setDefaultValue('en')
        );
        $this->addColumn(
            (new IpV4AddressColumn('ip'))
                ->allowsNullValues()
        );
        $this->addColumn(
            (new StringColumn('role'))
                ->convertsEmptyStringValuesToNull()
                ->setDefaultValue('guest')
        );
        $this->addColumn(
            (new BooleanColumn('is_active'))
                ->setDefaultValue(true)
        );
        $this->addColumn(
            (new StringColumn('name'))
                ->setDefaultValue('')
        );
        $this->addColumn(
            (new EmailColumn('email'))
                ->allowsNullValues()
        );
        $this->addColumn(
            (new StringColumn('timezone'))
                ->convertsEmptyStringValuesToNull()
                ->setDefaultValue('UTC')
        );
        $this->addColumn(
            new TestImageColumn('avatar')
        );
        $this->addColumn(
            new TestVirtualColumnWithAfterSave('some_file')
        );
        $this->addColumn(
            (new StringColumn('not_changeable_column'))
                ->valuesAreReadOnly()
        );
        $this->addColumn(
            new VirtualColumn(
                'not_existing_column',
                function () {
                    return false;
                },
                function () {
                    return null;
                }
            )
        );
        $this->addColumn(
            (new TextColumn('big_data'))
                ->valuesAreHeavy()
                ->setDefaultValue('this is big data value! really! I\'m not joking!')
        );
    }

    protected function registerRelations(): void
    {
        $this->addRelation(
            new Relation(
                'parent_id',
                Relation::BELONGS_TO,
                TestingAdminsTable::class,
                'id',
                'Parent'
            )
        );
        $this->addRelation(
            new Relation(
                'id',
                Relation::HAS_MANY,
                TestingAdminsTable::class,
                'parent_id',
                'Children'
            )
        );
        $this->addRelation(
            new Relation(
                'login',
                Relation::BELONGS_TO,
                TestingAdminsTable::class,
                'id',
                'VeryLongRelationNameSoItMustBeShortenedButWeNeedAtLeast60Characters'
            )
        );
    }
}