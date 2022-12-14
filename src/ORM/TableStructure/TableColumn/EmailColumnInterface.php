<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn;

interface EmailColumnInterface extends TableColumnInterface
{
    public function normalizeEmail(string $value, bool $isFromDb): ?string;
}