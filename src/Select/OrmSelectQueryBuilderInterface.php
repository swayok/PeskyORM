<?php

declare(strict_types=1);

namespace PeskyORM\Select;

use PeskyORM\ORM\Record\RecordInterface;
use PeskyORM\ORM\Table\TableInterface;

interface OrmSelectQueryBuilderInterface extends SelectQueryBuilderInterface
{
    public function __construct(TableInterface $table);

    public function getTable(): TableInterface;

    public function fetchOneAsDbRecord(): RecordInterface;
}