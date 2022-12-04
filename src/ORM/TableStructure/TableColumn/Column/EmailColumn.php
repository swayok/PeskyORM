<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\Utils\ValueTypeValidators;

class EmailColumn extends StringColumn
{
    protected bool $trimValues = true;
    protected bool $lowercaseValues = true;
    protected ?bool $convertEmptyStringValueToNull = true;

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isEmail($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_EMAIL
                ),
            ];
        }
        return [];
    }
}