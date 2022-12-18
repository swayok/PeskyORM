<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

use PeskyORM\ORM\TableStructure\TableStructureInterface;

class TableStructureConfigException extends OrmException
{
    public function __construct(
        string $message,
        private ?TableStructureInterface $tableStructure
    ) {
        parent::__construct($message, static::CODE_INVALID_TABLE_STRUCTURE_CONFIG);
    }

    public function getTableColumn(): ?TableStructureInterface
    {
        return $this->tableStructure;
    }
}