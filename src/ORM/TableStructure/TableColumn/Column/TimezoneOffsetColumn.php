<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use Carbon\Carbon;
use Carbon\CarbonTimeZone;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\Utils\ValueTypeValidators;

/**
 * It accepts both timezone names and offsets.
 * @see ValueTypeValidators::isTimezone()
 * Valid values are converted to timezone offset: '+dd:dd' or '-dd:dd'.
 */
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

    public function shouldConvertToIntegerValues(): bool
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
        if (!ValueTypeValidators::isTimezone($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_TIMEZONE
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
            $validatedValue = CarbonTimeZone::create($validatedValue);
            $isCarbon = true;
        }
        if ($isCarbon) {
            // convert to format: "+HH:MM" / "-HH:MM"
            return $this->convertToIntegerIfNeeded($validatedValue->toOffsetName());
        }
        if (is_numeric($validatedValue)) {
            // from -86400 to +86400
            if ($this->shouldConvertToIntegerValues()) {
                return (int)$validatedValue;
            }
            // convert to format: "+HH:MM" / "-HH:MM"
            $validatedValue = (int)$validatedValue;
            $carbon = Carbon::createFromTimestampUTC(abs($validatedValue));
            return ($validatedValue >= 0 ? '+' : '-') . $carbon->format('H:i');
        }
        // string (not empty)
        if (in_array($validatedValue[0], ['+', '-'])) {
            // "+HH:MM" / "-HH:MM"
            return $this->convertToIntegerIfNeeded($validatedValue);
        }
        // timezone name or shortcut: "Continent/City", "UTC"
        $carbon = new CarbonTimeZone($validatedValue);
        return $this->convertToIntegerIfNeeded($carbon->toOffsetName());
    }

    protected function convertToIntegerIfNeeded(string|int $validatedValue): int|string
    {
        if (!$this->shouldConvertToIntegerValues()) {
            return $validatedValue;
        }
        if (is_numeric($validatedValue)) {
            return (int)$validatedValue;
        }
        // Earlier we already validated format, so it should not crash
        // Get sign, hours and minutes
        preg_match('%^([-+])(\d\d):(\d\d)$%', $validatedValue, $matches);
        [, $sign, $hours, $minutes] = $matches;
        $validatedValue = (int)$hours * 3600 + (int)$minutes * 60;
        return $sign === '-' ? -$validatedValue : $validatedValue;
    }
}