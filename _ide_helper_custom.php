<?php
/** @noinspection PhpFullyQualifiedNameUsageInspection */

/** @noinspection AutoloadingIssuesInspection */

/** @noinspection PhpUnused */

class PeskyORMIdeHelperRecord extends \PeskyORM\ORM\Record
{
    
    use \PeskyORM\ORM\Traits\DbViewRecordProtection;
    use \PeskyORM\ORM\KeyValueTableHelpers\KeyValueRecordHelpers;
}

class PeskyORMIdeHelperTableStructure extends \PeskyORM\ORM\TableStructure
{
    
    private function id(): \PeskyORM\ORM\Column {
        return \PeskyORM\ORM\Column::create(\PeskyORM\ORM\Column::TYPE_INT);
    }
    
    public static function getTableName(): string
    {
        return '???';
    }
}

class PeskyORMIdeHelperTable extends \PeskyORM\ORM\Table implements \PeskyORM\ORM\KeyValueTableHelpers\KeyValueTableInterface
{
    
    use \PeskyORM\ORM\KeyValueTableHelpers\KeyValueTableHelpers;
    
    public function newRecord(): PeskyORMIdeHelperRecord
    {
        return PeskyORMIdeHelperRecord::newEmptyRecord();
    }
    
    public function getTableStructure(): PeskyORMIdeHelperTableStructure
    {
        return PeskyORMIdeHelperTableStructure::getInstance();
    }
}
