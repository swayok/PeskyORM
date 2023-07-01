<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\BooleanColumnTemplate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;

class BooleanColumn extends BooleanColumnTemplate
{
    use CanBeNullable;
}
