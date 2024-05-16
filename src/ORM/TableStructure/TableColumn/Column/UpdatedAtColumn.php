<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\DbExpr;

class UpdatedAtColumn extends TimestampColumn
{
    public function __construct(string $name = 'updated_at')
    {
        parent::__construct($name);
        $this
            ->valuesAreReadOnly()
            ->setValueAutoUpdater(function () {
                return DbExpr::create('NOW()');
            });
    }
}
