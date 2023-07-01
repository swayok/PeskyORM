<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\IntegerColumnTemplate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrimaryKey;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrivate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeUnique;
use PeskyORM\ORM\TableStructure\TableColumn\UniqueTableColumnInterface;

class IntegerColumn extends IntegerColumnTemplate implements UniqueTableColumnInterface
{
    use CanBeUnique;
    use CanBeNullable;
    use CanBePrivate;
    use CanBePrimaryKey;
}
