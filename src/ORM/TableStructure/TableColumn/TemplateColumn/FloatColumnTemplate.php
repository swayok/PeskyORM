<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;

abstract class FloatColumnTemplate extends RealTableColumnAbstract
{
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