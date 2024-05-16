<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ConvertsValueToClassInstanceInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\JsonObjectColumnTemplate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeHeavy;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanConvertValueToClassInstance;

/**
 * This column allows only key-value objects.
 * Example: '{"key1": "value", "key2": ["v1", "v2"], "key3": {"k1": ""}, "0": "", ...}'.
 * Note: value '[]' (empty array) is allowed and handled like empty object: '{}'.
 */
class JsonObjectColumn extends JsonObjectColumnTemplate implements ConvertsValueToClassInstanceInterface
{
    use CanBeNullable;
    use CanBeHeavy;
    use CanConvertValueToClassInstance;
}
