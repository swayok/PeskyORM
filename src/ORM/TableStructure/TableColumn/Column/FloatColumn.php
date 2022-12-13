<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrivate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeUnique;
use PeskyORM\ORM\TableStructure\TableColumn\UniqueTableColumnInterface;

class FloatColumn extends RealTableColumnAbstract implements UniqueTableColumnInterface
{
    use CanBeUnique;
    use CanBeNullable;
    use CanBePrivate;

    public function getDataType(): string
    {
        return TableColumnDataType::FLOAT;
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        // any numeric value can be converted to float
        if (!is_numeric($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_FLOAT
                ),
            ];
        }
        return [];
    }

    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): float {
        return (float)$validatedValue;
    }
}