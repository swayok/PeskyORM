<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBePrivate;
use PeskyORM\Utils\ValueTypeValidators;

class IpV4AddressColumn extends RealTableColumnAbstract
{
    use CanBeNullable;
    use CanBePrivate;

    public function getDataType(): string
    {
        return TableColumnDataType::IPV4_ADDRESS;
    }

    protected function normalizeValueForValidation(mixed $value, bool $isFromDb): mixed
    {
        $value = parent::normalizeValueForValidation($value, $isFromDb);
        if (is_string($value)) {
            return $this->normalizeStringValue($value, $isFromDb);
        }
        return $value;
    }

    protected function normalizeStringValue(string $value, bool $isFromDb): ?string
    {
        if ($isFromDb) {
            return $value;
        }
        $value = trim($value);
        return $value === '' ? null : $value;
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isIpV4Address($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_IPV4_ADDRESS
                ),
            ];
        }
        return [];
    }

    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): string {
        return (string)$validatedValue;
    }
}