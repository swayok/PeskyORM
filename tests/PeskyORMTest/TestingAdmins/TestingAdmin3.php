<?php

declare(strict_types=1);

namespace PeskyORM\Tests\PeskyORMTest\TestingAdmins;

use PeskyORM\ORM\Record\Record;

class TestingAdmin3 extends Record
{
    public function __construct()
    {
        parent::__construct(TestingAdmins3Table::getInstance());
    }

    public function runColumnSavingExtenders(array $dataSavedToDb, bool $isUpdate): void
    {
        parent::runColumnSavingExtenders($dataSavedToDb, $isUpdate);
    }
}