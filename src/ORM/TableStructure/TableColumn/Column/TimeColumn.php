<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use Carbon\CarbonImmutable;
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

class TimeColumn extends RealTableColumnAbstract implements UniqueTableColumnInterface
{
    use CanBeUnique;
    use CanBeNullable;
    use CanHaveTimeZone;

    public const FORMAT = 'H:i:s';
    public const FORMAT_WITH_TZ = 'H:i:sP';

    public function getDataType(): string
    {
        return TableColumnDataType::TIME;
    }

    protected function registerDefaultValueFormatters(): void
    {
        $this->formatters = ColumnValueFormatters::getTimeFormatters();
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isTimestamp($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_TIME
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
            $validatedValue = CarbonImmutable::createFromTimestampUTC($validatedValue);
        } else {
            // string or DateTimeInterface
            $validatedValue = CarbonImmutable::parse($validatedValue);
        }
        return $validatedValue->format(
            $this->isTimezoneExpected() ? static::FORMAT_WITH_TZ : static::FORMAT
        );
    }
}