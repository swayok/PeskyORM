<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\JsonArrayColumnTemplate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeHeavy;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;

/**
 * This column allows only indexed arrays.
 * Example: '["value1", "value2", {"key1": ""}, ...]'.
 */
class JsonArrayColumn extends JsonArrayColumnTemplate
{
    use CanBeNullable;
    use CanBeHeavy;
}