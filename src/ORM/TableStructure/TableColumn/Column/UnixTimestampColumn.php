<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueFormatters;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;

class UnixTimestampColumn extends IntegerColumn
{
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
        $errors = parent::validateValueDataType(
            $normalizedValue,
            $isForCondition,
            $isFromDb
        );
        if (!empty($errors)) {
            return $errors;
        }
        if (!$isForCondition && (int)$normalizedValue <= 0) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_POSITIVE_INTEGER
                ),
            ];
        }
        return [];
    }
}