<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

/**
 * @see TableColumnInterface::isHeavyValues()
 */
trait CanBeHeavy
{
    protected bool $isHeavy = false;

    public function isHeavyValues(): bool
    {
        return $this->isHeavy;
    }

    public function valuesAreHeavy(): static
    {
        $this->isHeavy = true;
        return $this;
    }
}