<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\TemplateColumn;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Utils\ValueTypeValidators;

class BooleanColumnTemplate extends RealTableColumnAbstract
{
    public function getDataType(): string
    {
        return TableColumnDataType::BOOL;
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        $value = parent::normalizeValueForValidation($value, $isFromDb);
        if (is_string($value) && trim($value) === '') {
            return null;
        }
        return $value;
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isBoolean($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_BOOLEAN
                ),
            ];
        }
        return [];
    }

    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): bool {
        return (bool)$validatedValue;
    }
}