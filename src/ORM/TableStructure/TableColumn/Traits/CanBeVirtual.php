<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

use PeskyORM\ORM\TableStructure\TableColumn\TableColumnInterface;

/**
 * @see TableColumnInterface::isReal()
 */
trait CanBeVirtual
{
    /**
     * Is this column exists in DB or not.
     * If not - column valueGetter() must be provided to return a value of this column
     * Record will not save columns that does not exist in DB
     */
    protected bool $isReal = true;

    public function doesNotExistInDb(): static
    {
        $this->isReal = false;
        return $this;
    }

    /**
     * Is this column exists in DB?
     */
    public function isReal(): bool
    {
        return $this->isReal;
    }
}