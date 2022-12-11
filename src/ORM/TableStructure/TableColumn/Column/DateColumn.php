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
use PeskyORM\ORM\TableStructure\TableColumn\UniqueTableColumnInterface;
use PeskyORM\Utils\ValueTypeValidators;

class DateColumn extends RealTableColumnAbstract implements UniqueTableColumnInterface
{
    use CanBeUnique;
    use CanBeNullable;

    public const FORMAT = 'Y-m-d';

    protected bool $hasTimezone = false;

    public function getDataType(): string
    {
        return TableColumnDataType::DATE;
    }

    protected function registerDefaultValueFormatters(): void
    {
        $this->formatters = ColumnValueFormatters::getDateFormatters();
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isTimestamp($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_DATE
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
        return $validatedValue->format(static::FORMAT);
    }
}