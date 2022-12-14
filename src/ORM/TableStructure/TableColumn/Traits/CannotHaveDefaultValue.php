<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

use PeskyORM\Exception\TableColumnConfigException;

trait CannotHaveDefaultValue
{
    public function hasDefaultValue(): bool
    {
        return false;
    }

    public function setDefaultValue(mixed $defaultValue): static
    {
        throw new TableColumnConfigException(
            'Column ' . $this->getNameForException()
            . ' is not allowed to have default value.',
            $this
        );
    }
}