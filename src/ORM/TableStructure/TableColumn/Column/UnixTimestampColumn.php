<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeNullable;
use PeskyORM\ORM\TableStructure\TableColumn\Traits\CanBeUnique;
use PeskyORM\ORM\TableStructure\TableColumn\UniqueTableColumnInterface;
use PeskyORM\Utils\ValueTypeValidators;

class UnixTimestampColumn extends RealTableColumnAbstract implements UniqueTableColumnInterface
{
    use CanBeUnique;
    use CanBeNullable;

    public function getDataType(): string
    {
        return TableColumnDataType::UNIX_TIMESTAMP;
    }

    protected function registerDefaultValueFormatters(): void
    {
        $this->formatters = ColumnValueFormatters::getUnixTimestampFormatters();
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isTimestamp($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_TIMESTAMP_OR_INTEGER
                ),
            ];
        }
        return [];
    }

    /**
     * @param int|string|CarbonInterface|\DateTimeInterface $validatedValue
     */
    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): int {
        if (is_numeric($validatedValue)) {
            return (int)$validatedValue;
        }
        if (!($validatedValue instanceof CarbonInterface)) {
            // string or DateTimeInterface
            $validatedValue = Carbon::parse($validatedValue)
                ->setTimezone('UTC');
        }
        return $validatedValue->unix();
    }
}