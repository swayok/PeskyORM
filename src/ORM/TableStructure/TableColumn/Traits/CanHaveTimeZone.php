<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Traits;

trait CanHaveTimeZone
{
    protected bool $hasTimezone = false;

    public function withTimezone(): static
    {
        $this->hasTimezone = true;
        return $this;
    }

    public function isTimezoneExpected(): bool
    {
        return $this->hasTimezone;
    }
}