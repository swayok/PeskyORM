<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

interface ConvertsValueToClassInstanceInterface
{
    public function getClassNameForValueToClassInstanceConverter(): ?string;
}