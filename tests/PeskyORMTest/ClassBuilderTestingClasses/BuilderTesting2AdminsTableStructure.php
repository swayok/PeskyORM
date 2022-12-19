<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\ClassBuilderTestingClasses;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\Column\BooleanColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\CreatedAtColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IdColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\IntegerColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\PasswordColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\StringColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\TextColumn;
use PeskyORM\ORM\TableStructure\TableColumn\Column\UpdatedAtColumn;
use PeskyORM\Tests\PeskyORMTest\TestingAdmins\TestingAdminsTableStructure;

class BuilderTesting2AdminsTableStructure extends TestingAdminsTableStructure
{
    public function getTableName(): string
    {
        return 'admins';
    }

    public function getSchema(): string
    {
        return 'public';
    }

    protected function registerColumns(): void
    {
        $this->addColumn(
            (new IdColumn('id'))
        );
        $this->addColumn(
            (new StringColumn('login'))
                ->uniqueValues()
        );
        $this->addColumn(
            (new PasswordColumn('password'))
        );
        $this->addColumn(
            (new IntegerColumn('parent_id'))
                ->allowsNullValues()
        );
        $this->addColumn(
            (new CreatedAtColumn('created_at'))
                ->withTimezone()
        );
        $this->addColumn(
            (new UpdatedAtColumn('updated_at'))
                ->setDefaultValue(DbExpr::create('now()'))
                ->withTimezone()
        );
        $this->addColumn(
            (new StringColumn('remember_token'))
                ->allowsNullValues()
                ->setDefaultValue('')
        );
        $this->addColumn(
            (new BooleanColumn('is_superadmin'))
                ->setDefaultValue(false)
        );
        $this->addColumn(
            (new StringColumn('language'))
                ->allowsNullValues()
                ->setDefaultValue('en')
                ->convertsEmptyStringValuesToNull()
        );
        $this->addColumn(
            (new StringColumn('ip'))
                ->allowsNullValues()
                ->setDefaultValue('192.168.1.1')
                ->convertsEmptyStringValuesToNull()
        );
        $this->addColumn(
            (new StringColumn('role'))
                ->setDefaultValue('')
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
            (new StringColumn('email'))
                ->allowsNullValues()
                ->uniqueValues()
        );
        $this->addColumn(
            (new StringColumn('timezone'))
                ->setDefaultValue('UTC')
                ->convertsEmptyStringValuesToNull()
        );
        $this->addColumn(
            (new StringColumn('not_changeable_column'))
                ->allowsNullValues()
                ->setDefaultValue('not changable')
                ->convertsEmptyStringValuesToNull()
        );
        $this->addColumn(
            (new TextColumn('big_data'))
                ->setDefaultValue('biiiiiiig data')
                ->convertsEmptyStringValuesToNull()
        );
    }

    protected function registerRelations(): void
    {
    }
}