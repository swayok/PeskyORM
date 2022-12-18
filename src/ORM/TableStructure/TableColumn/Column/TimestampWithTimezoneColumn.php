<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

class TimestampWithTimezoneColumn extends TimestampColumn
{
    public function __construct(string $name)
    {
        parent::__construct($name);
        $this->withTimezone();
    }
}