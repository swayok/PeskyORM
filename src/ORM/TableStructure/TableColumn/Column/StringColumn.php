<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\StringColumnTemplate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrimaryKey;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrivate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeUnique;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanNormalizeStringValue;
use PeskyORM\ORM\TableStructure\TableColumn\UniqueTableColumnInterface;

class StringColumn extends StringColumnTemplate implements UniqueTableColumnInterface
{
    use CanBeNullable;
    use CanBeUnique;
    use CanBePrivate;
    use CanBePrimaryKey;
    use CanNormalizeStringValue;
}