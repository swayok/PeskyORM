<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ConvertsValueToClassInstanceInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn\MixedJsonColumnTemplate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeHeavy;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanConvertValueToClassInstance;

/**
 * This column allows any value accepted by json_decode() / json_encode()
 * including numbers, strings and boolean values.
 * Use more strict columns to accept only arrays or objects:
 * @see JsonArrayColumn
 * @see JsonObjectColumn
 */
class MixedJsonColumn extends MixedJsonColumnTemplate implements ConvertsValueToClassInstanceInterface
{
    use CanBeNullable;
    use CanBeHeavy;
    use CanConvertValueToClassInstance;

    protected bool $allowsOnlyJsonArraysAndObjects = false;

    /**
     * Allow: json array, json object.
     * Forbid: bool, number, string, others.
     * Nulls controlled by self::isNullableValues()
     */
    public function allowsOnlyJsonArraysAndObjects(): static
    {
        $this->allowsOnlyJsonArraysAndObjects = true;
        return $this;
    }

    public function isOnlyJsonArraysAndObjectsAllowed(): bool
    {
        return $this->allowsOnlyJsonArraysAndObjects;
    }
}