<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use Carbon\CarbonTimeZone;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\Utils\ValueTypeValidators;

class TimezoneOffsetColumn extends RealTableColumnAbstract
{
    use CanBeNullable;

    protected bool $convertToInteger = false;

    public function getDataType(): string
    {
        return TableColumnDataType::TIMEZONE_OFFSET;
    }

    public function convertsStringOffsetToInteger(): static
    {
        $this->convertToInteger = true;
        return $this;
    }

    public function isIntegerValues(): bool
    {
        return $this->convertToInteger;
    }

    protected function registerDefaultValueFormatters(): void
    {
        $this->formatters = ColumnValueFormatters::getTimeZoneFormatters();
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
    ): string|int {
        $isCarbon = $validatedValue instanceof CarbonTimeZone;
        if (!$isCarbon && $validatedValue instanceof \DateTimeZone) {
            $validatedValue = new CarbonTimeZone($validatedValue);
            $isCarbon = true;
        }
        if ($isCarbon) {
            // convert to format: "+HH:MM" / "-HH:MM"
            $validatedValue = $validatedValue->toOffsetName();
            if (!$this->isIntegerValues()) {
                // "+HH:MM" / "-HH:MM"
                return $validatedValue;
            }
        }
        if ($this->isIntegerValues()) {
            if (is_numeric($validatedValue)) {
                return (int)$validatedValue;
            }
            // convert to integer
            preg_match(
                ValueTypeValidators::TIMEZONE_FORMAT_REGEXP,
                $validatedValue,
                $matches
            );
            // earlier we already validated format so it should not crash
            [, $sign, $hours, $minutes] = $matches;
            $validatedValue = (int)$hours * 3600 + (int)$minutes * 60;
            return $sign === '-' ? -$validatedValue : $validatedValue;
        }
        if (is_numeric($validatedValue)) {
            // from -86400 to +86400
            // convert to format: "+HH:MM" / "-HH:MM"
            $validatedValue = (int)$validatedValue;
            return ($validatedValue >= 0 ? '+' : '-') . date('H:i', $validatedValue);
        }
        // "+HH:MM" / "-HH:MM" and $this->isIntegerValues() === false
        return $validatedValue;
    }
}