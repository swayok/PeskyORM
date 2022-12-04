<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeHeavy;

class TextColumn extends StringColumn
{
    use CanBeHeavy;

    public function getDataType(): string
    {
        return TableColumnDataType::TEXT;
    }
}