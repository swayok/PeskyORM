<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrivate;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeUnique;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeVirtual;
use PeskyORM\Utils\ValueTypeValidators;

class IntegerColumn extends TableColumnAbstract
{
    use CanBeUnique;
    use CanBeNullable;
    use CanBePrivate;
    use CanBeVirtual;

    public function getDataType(): string
    {
        return TableColumnDataType::INT;
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