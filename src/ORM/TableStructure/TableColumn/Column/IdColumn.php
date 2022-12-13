<?php

declare(strict_types=1);

namespace PeskyORM\ORM\TableStructure\TableColumn\Column;

use PeskyORM\DbExpr;
use PeskyORM\ORM\TableStructure\TableColumn\ColumnValueValidationMessages\ColumnValueValidationMessagesInterface;
use PeskyORM\ORM\TableStructure\TableColumn\RealTableColumnAbstract;
use PeskyORM\ORM\TableStructure\TableColumn\TableColumnDataType;
use PeskyORM\Utils\ValueTypeValidators;

class IdColumn extends RealTableColumnAbstract
{
    public function __construct(string $name = 'id')
    {
        parent::__construct($name);
    }

    public function getDataType(): string
    {
        return TableColumnDataType::INT;
    }

    public function isNullableValues(): bool
    {
        return true;
    }

    final public function isPrimaryKey(): bool
    {
        return true;
    }

    final public function isAutoUpdatingValues(): bool
    {
        return false;
    }

    protected function validateIfNullValueIsAllowed(bool $isFromDb, bool $isForCondition): bool
    {
        return !$isFromDb || $isForCondition;
    }

    protected function shouldValidateValue(mixed $value, bool $isFromDb): bool
    {
        if ($isFromDb) {
            return true;
        }
        // SelectQueryBuilderInterface instances are not allowed
        return !($value instanceof DbExpr);
    }

    protected function validateValueDataType(
        mixed $normalizedValue,
        bool $isForCondition,
        bool $isFromDb
    ): array {
        if (!ValueTypeValidators::isInteger($normalizedValue)) {
            return [
                $this->getValueValidationMessage(
                    ColumnValueValidationMessagesInterface::VALUE_MUST_BE_INTEGER
                ),
            ];
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

    protected function normalizeValidatedValueType(
        mixed $validatedValue,
        bool $isFromDb
    ): int {
        return (int)$validatedValue;
    }
}