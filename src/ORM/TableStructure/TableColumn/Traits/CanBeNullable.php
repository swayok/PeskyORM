<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

/**
 * @see TableColumnInterface::isNullableValues()
 */
trait CanBeNullable
{
    protected bool $valueCanBeNull = false;

    protected function allowsNullValues(): static
    {
        $this->valueCanBeNull = true;
        return $this;
    }

    public function isNullableValues(): bool
    {
        return $this->valueCanBeNull;
    }
}