<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

/**
 * @see TableColumnInterface::isPrimaryKey()
 */
trait CanBePrimaryKey
{
    protected bool $isPrimaryKey = false;

    public function isPrimaryKey(): bool
    {
        return $this->isPrimaryKey;
    }

    public function primaryKey(): static
    {
        $this->isPrimaryKey = true;
        return $this;
    }
}