<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */
/** @noinspection AutoloadingIssuesInspection */
/** @noinspection PhpUnused */
declare(strict_types=1);

abstract class PeskyORMIdeHelperRecord extends \PeskyORM\ORM\Record\Record
{
    
    use \PeskyORM\ORM\Record\DbViewRecordProtection;
    use \PeskyORM\ORM\Record\GettersForKeyValueRecordValues;
}

class PeskyORMIdeHelperTableStructure extends \PeskyORM\ORM\TableStructure\TableStructure
{
    
    private function id(): \PeskyORM\ORM\TableStructure\TableColumn\Column {
        return \PeskyORM\ORM\TableStructure\TableColumn\Column::create(\PeskyORM\ORM\TableStructure\TableColumn\Column::TYPE_INT);
    }
    
    public static function getTableName(): string
    {
        return '???';
    }
}

class PeskyORMIdeHelperTable extends \PeskyORM\ORM\Table\Table implements \PeskyORM\ORM\Table\KeyValueTableInterface
{
    
    use \PeskyORM\ORM\Table\KeyValueTableWorkflow;
    
    public function newRecord(): \PeskyORM\ORM\Record\RecordInterface
    {
        return PeskyORMIdeHelperRecord::newEmptyRecord();
    }
    
    public function getTableStructure(): \PeskyORM\ORM\TableStructure\TableStructureInterface
    {
        return PeskyORMIdeHelperTableStructure::getInstance();
    }
}
