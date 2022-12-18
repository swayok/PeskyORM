<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\VirtualTable;

use PeskyORM\ORM\Record\Record;
use PeskyORM\ORM\Table\Table;

class VirtualTable extends Table
{
    public function __construct()
    {
        parent::__construct(
            new VirtualTableStructure(),
            Record::class
        );
    }
}