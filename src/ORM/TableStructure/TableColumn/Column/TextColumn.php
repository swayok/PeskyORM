<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\StringColumnTemplate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeHeavy;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrivate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanNormalizeStringValue;

class TextColumn extends StringColumnTemplate
{
    use CanBeHeavy;
    use CanBeNullable;
    use CanBePrivate;
    use CanNormalizeStringValue;

    public function getDataType(): string
    {
        return TableColumnDataType::TEXT;
    }

    protected function shouldStoreRawValueInValueContainer(): bool
    {
        return false;
    }
}