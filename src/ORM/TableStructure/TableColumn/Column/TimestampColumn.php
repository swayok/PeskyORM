<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use DateTimeInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeUnique;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanHaveTimeZone;
use PeskyORM\ORM\TableStructure\TableColumn\UniqueTableColumnInterface;
use PeskyORM\Utils\ValueTypeValidators;

class TimestampColumn extends RealTableColumnAbstract implements UniqueTableColumnInterface
{
    use CanBeUnique;
    use CanBeNullable;
    use CanHaveTimeZone;

    public const FORMAT = 'Y-m-d H:i:s';
    public const FORMAT_WITH_TZ = 'Y-m-d H:i:sP';

    public function getDataType(): string
    {
        return TableColumnDataType::TIMESTAMP;
    }

    protected function registerDefaultValueFormatters(): void
    {
        $this->formatters = ColumnValueFormatters::getTimestampFormatters();
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isTimestamp($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_TIMESTAMP
                ),
            ];
        }
        return [];
    }

    /**
     * @param int|string|CarbonInterface|DateTimeInterface $validatedValue
     */
    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): string {
        if ($isFromDb && is_string($validatedValue) && !is_numeric($validatedValue)) {
            return $validatedValue;
        }

        if (is_numeric($validatedValue)) {
            $validatedValue = Carbon::createFromTimestampUTC($validatedValue);
        } elseif (!($validatedValue instanceof CarbonInterface)) {
            // string or DateTimeInterface
            $validatedValue = Carbon::parse($validatedValue);
            if (
                !$validatedValue->isLocal()
                && !$this->isTimezoneExpected()
            ) {
                // Column does not support timezones but received
                // a string with timezone (like '2022-12-07 15:00:00+01:00')
                // and timezone in string differs from local timezone.
                // To preserve corret time we need to convert it to local timezone.
                $validatedValue->setTimezone(null);
            }
        }
        return $validatedValue->format(
            $this->isTimezoneExpected() ? static::FORMAT_WITH_TZ : static::FORMAT
        );
    }
}