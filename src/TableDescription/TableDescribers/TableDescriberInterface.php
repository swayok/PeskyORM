<?php

namespace PeskyORM\TableDescription\TableDescribers;

use PeskyORM\Core\DbAdapterInterface;
use PeskyORM\TableDescription\TableDescription;

interface TableDescriberInterface
{

    public function __construct(DbAdapterInterface $adapter);

    public function getTableDescription(string $tableName, ?string $schema = null): TableDescription;

}