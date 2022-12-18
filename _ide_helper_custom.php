<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

/** @noinspection AutoloadingIssuesInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

use JetBrains\PhpStorm\ArrayShape;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

abstract class PeskyORMIdeHelperRecord extends \PeskyORM\ORM\Record\Record
{
    use \PeskyORM\ORM\Record\DbViewRecordProtection;
}

class PeskyORMIdeHelperTableStructure extends \PeskyORM\ORM\TableStructure\TableStructure
{
    public function getTableName(): string
    {
        return '???';
    }

    #[ArrayShape([
        'column' => TableColumnInterface::class,
        'format' => 'null|string',
    ])]
    public function getColumnAndFormat(string $columnNameOrAlias): array
    {
        return [];
    }

    protected function registerColumns(): void
    {
    }

    protected function registerRelations(): void
    {
    }
}

class PeskyORMIdeHelperTable extends \PeskyORM\ORM\Table\Table
{
    public function newRecord(): \PeskyORM\ORM\Record\RecordInterface
    {
        return PeskyORMIdeHelperRecord::newEmptyRecord();
    }

    public function getTableStructure(): \PeskyORM\ORM\TableStructure\TableStructureInterface
    {
        return new PeskyORMIdeHelperTableStructure();
    }
}
