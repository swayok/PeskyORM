<?php

declare(strict_types=1);

namespace PeskyORM\Exception;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

class TableColumnConfigException extends OrmException
{
    public function __construct(
        string $message,
        private ?TableColumnInterface $column
    ) {
        parent::__construct($message, static::CODE_INVALID_TABLE_COLUMN_CONFIG);
    }

    public function getTableColumn(): ?TableColumnInterface
    {
        return $this->column;
    }
}