<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

trait CanLowercaseStringValue
{
    protected bool $lowercaseValues = false;

    public function lowercasesValues(): static
    {
        $this->lowercaseValues = true;
        return $this;
    }

    protected function shouldLowercaseValues(bool $isFromDb): bool
    {
        return $this->lowercaseValues;
    }
}