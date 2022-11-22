<?php

declare(strict_types=1);

namespace PeskyORM\TableDescription\TableDescribers;

use PeskyORM\Adapter\DbAdapterInterface;
use PeskyORM\TableDescription\TableDescription;

interface TableDescriberInterface
{

    public function __construct(DbAdapterInterface $adapter);

    public function getTableDescription(string $tableName, ?string $schema = null): TableDescription;

}