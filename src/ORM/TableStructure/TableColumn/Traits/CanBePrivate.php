<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

/**
 * @see TableColumnInterface::isPrivateValues()
 */
trait CanBePrivate
{
    protected bool $isPrivate = false;

    public function isPrivateValues(): bool
    {
        return $this->isPrivate;
    }

    public function valuesArePrivate(): static
    {
        $this->isPrivate = true;
        return $this;
    }
}