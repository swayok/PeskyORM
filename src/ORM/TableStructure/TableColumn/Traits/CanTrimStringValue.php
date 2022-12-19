<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

trait CanTrimStringValue
{
    protected bool $trimValues = false;

    public function trimsValues(): static
    {
        $this->trimValues = true;
        return $this;
    }

    /** @noinspection PhpUnusedParameterInspection */
    protected function shouldTrimStringValues(bool $isFromDb): bool
    {
        return $this->trimValues;
    }
}