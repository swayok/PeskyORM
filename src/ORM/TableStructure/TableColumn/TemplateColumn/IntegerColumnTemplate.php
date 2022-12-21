<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Utils\ValueTypeValidators;

abstract class IntegerColumnTemplate extends RealTableColumnAbstract
{
    public function getDataType(): string
    {
        return TableColumnDataType::INT;
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isInteger($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_INTEGER
                ),
            ];
        }
        return [];
    }

    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): int {
        return (int)$validatedValue;
    }
}