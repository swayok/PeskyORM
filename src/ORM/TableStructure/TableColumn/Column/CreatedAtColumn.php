<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\DbExpr;

class CreatedAtColumn extends TimestampColumn
{
    public function __construct(string $name = 'created_at')
    {
        parent::__construct($name);
        $this
            ->valuesAreReadOnly()
            ->setDefaultValue(DbExpr::create('NOW()'));
    }
}