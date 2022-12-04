<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use Carbon\CarbonTimeZone;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeVirtual;
use PeskyORM\Utils\ValueTypeValidators;

class TimezoneOffsetColumn extends TableColumnAbstract
{
    use CanBeNullable;
    use CanBeVirtual;

    protected bool $hasTimezone = false;

    public function getDataType(): string
    {
        return TableColumnDataType::TIMEZONE_OFFSET;
    }

    public function withTimezone(): static
    {
        $this->hasTimezone = true;
        return $this;
    }

    public function hasTimezone(): bool
    {
        return $this->hasTimezone;
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isTimezoneOffset($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_TIMEZONE_OFFSET
                ),
            ];
        }
        return [];
    }

    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): string {
        if ($validatedValue instanceof CarbonTimeZone) {
            return $validatedValue->toOffsetName();
        }
        if (is_numeric($validatedValue)) {
            // from -86400 to +86400
            $validatedValue = (int)$validatedValue;
            return ($validatedValue >= 0 ? '+' : '-') . date('H:i', $validatedValue);
        }
        return $validatedValue;
    }
}